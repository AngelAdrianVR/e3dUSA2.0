<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class MigrateLegacyContacts extends Command
{
    /**
     * The name and signature of the console command.
     * M°5 Todo correcto, solo que tiene la logica anterior. los productos de agregan a la matriz y los contactos a las sucursales.
     * @var string
     */
    protected $signature = 'app:migrate-legacy-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los contactos y sus detalles desde la base de datos antigua, adaptándolos a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Contactos y sus Detalles...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "contacts" y "contact_details" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('contact_details')->truncate();
                DB::table('contacts')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas "contacts" y "contact_details" han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            // Conexiones a las bases de datos definidas en config/database.php
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql'); // Conexión por defecto

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->line('');
                $this->info('Migrando contactos...');
                $old_contacts = $oldDb->table('contacts')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_contacts->count());

                // Para asignar el primer contacto de cada entidad como primario
                $processedContactables = [];

                foreach ($old_contacts as $contact) {
                    $new_contactable_id = null;
                    $new_contactable_type = null;

                    // --- Lógica para encontrar el nuevo ID y tipo polimórfico ---
                    // CORRECCIÓN: Se compara con el namespace completo que viene de la BD antigua.
                    if ($contact->contactable_type === 'App\\Models\\CompanyBranch') {
                        $new_contactable_type = 'App\\Models\\Branch';
                        $oldBranch = $oldDb->table('company_branches')->find($contact->contactable_id);

                        if ($oldBranch) {
                            $newBranch = $newDb->table('branches')->where('name', $oldBranch->name)->first();
                            if ($newBranch) {
                                $new_contactable_id = $newBranch->id;
                            } else {
                                $this->warn("\nAdvertencia: No se encontró Sucursal (Branch) con nombre '{$oldBranch->name}' para contacto ID antiguo {$contact->id}. Se omitirá.");
                                Log::warning("Contacto antiguo omitido (ID: {$contact->id}): No se encontró Branch '{$oldBranch->name}'.");
                                $progressBar->advance();
                                continue;
                            }
                        }
                    // CORRECCIÓN: Se asume que Supplier también viene con el namespace completo.
                    } elseif ($contact->contactable_type === 'App\\Models\\Supplier') {
                        $new_contactable_type = 'App\\Models\\Supplier';
                        $oldSupplier = $oldDb->table('suppliers')->find($contact->contactable_id);

                        if ($oldSupplier) {
                            $newSupplier = $newDb->table('suppliers')->where('name', $oldSupplier->name)->first();
                            if ($newSupplier) {
                                $new_contactable_id = $newSupplier->id;
                            } else {
                                $this->warn("\nAdvertencia: No se encontró Proveedor (Supplier) con nombre '{$oldSupplier->name}' para contacto ID antiguo {$contact->id}. Se omitirá.");
                                Log::warning("Contacto antiguo omitido (ID: {$contact->id}): No se encontró Supplier '{$oldSupplier->name}'.");
                                $progressBar->advance();
                                continue;
                            }
                        }
                    } else {
                         $this->warn("\nAdvertencia: Tipo '{$contact->contactable_type}' desconocido para contacto ID antiguo {$contact->id}. Se omitirá.");
                         $progressBar->advance();
                         continue;
                    }
                    
                    if (!$new_contactable_id) {
                        $this->warn("\nAdvertencia: No se pudo encontrar entidad asociada para contacto antiguo ID {$contact->id}. Se omitirá.");
                        $progressBar->advance();
                        continue;
                    }

                    // --- Preparar datos para la nueva tabla 'contacts' ---
                    $birthdate = null;
                    if (!empty($contact->birthdate_month) && !empty($contact->birthdate_day)) {
                        try {
                            $birthdate = Carbon::createFromDate(2000, $contact->birthdate_month, $contact->birthdate_day)->format('Y-m-d');
                        } catch (Throwable $e) {
                             $this->warn("\nFecha de nacimiento inválida para contacto ID antiguo {$contact->id}. Se dejará nula.");
                             $birthdate = null;
                        }
                    }
                    
                    $contactableKey = $new_contactable_type . '-' . $new_contactable_id;
                    $is_primary = !in_array($contactableKey, $processedContactables);
                    if ($is_primary) {
                        $processedContactables[] = $contactableKey;
                    }

                    // Insertar en la tabla 'contacts' y obtener el nuevo ID
                    $newContactId = $newDb->table('contacts')->insertGetId([
                        'contactable_id' => $new_contactable_id,
                        'contactable_type' => $new_contactable_type,
                        'name' => $contact->name,
                        'charge' => $contact->charge,
                        'birthdate' => $birthdate,
                        'is_primary' => $is_primary,
                        'created_at' => $contact->created_at,
                        'updated_at' => $contact->updated_at,
                    ]);
                    
                    // --- Migrar emails y teléfonos a la nueva tabla 'contact_details' ---
                    if ($contact->email) {
                        $newDb->table('contact_details')->insert(['contact_id' => $newContactId, 'type' => 'Correo', 'value' => $contact->email, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    if ($contact->additional_emails) {
                        $additionalEmails = json_decode($contact->additional_emails, true);
                        if (is_array($additionalEmails)) {
                            foreach ($additionalEmails as $email) {
                                if (!empty($email)) {
                                     $newDb->table('contact_details')->insert(['contact_id' => $newContactId, 'type' => 'Correo', 'value' => $email, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()]);
                                }
                            }
                        }
                    }

                    if ($contact->phone) {
                        $newDb->table('contact_details')->insert(['contact_id' => $newContactId, 'type' => 'Teléfono', 'value' => $contact->phone, 'is_primary' => true, 'created_at' => now(), 'updated_at' => now()]);
                    }
                    if ($contact->additional_phones) {
                        $additionalPhones = json_decode($contact->additional_phones, true);
                        if(is_array($additionalPhones)) {
                             foreach ($additionalPhones as $phone) {
                                if (!empty($phone)) {
                                    $newDb->table('contact_details')->insert(['contact_id' => $newContactId, 'type' => 'Teléfono', 'value' => $phone, 'is_primary' => false, 'created_at' => now(), 'updated_at' => now()]);
                                }
                            }
                        }
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->info(' -> Contactos y sus detalles migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE CONTACTOS COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de contactos: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

