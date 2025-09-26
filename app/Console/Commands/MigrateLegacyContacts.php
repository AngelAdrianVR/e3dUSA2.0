<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MigrateLegacyContacts extends Command
{
    /**
     * The name and signature of the console command.
     * N°5 - algunos si los asignó bien, pero solo creó 757 de 910 contactos
     * @var string
     */
    protected $signature = 'app:migrate-legacy-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates contacts polymorphically from the old database to the new structure.';

    /**
     * Cache for new branch IDs to avoid repeated DB queries.
     * @var array
     */
    private $branchNameCache = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de contactos polimórficos...");
        $this->warn("IMPORTANTE: Asegúrate de haber ejecutado primero la migración de clientes (branches) y proveedores.");

        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "contacts" y "contact_details" antes de empezar?')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('contact_details')->truncate();
            DB::table('contacts')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->warn('Las tablas "contacts" y "contact_details" han sido limpiadas.');
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->migrateContacts($oldDb, $newDb);
            });

            $this->info("\n\n¡MIGRACIÓN DE CONTACTOS COMPLETADA EXITOSAMENTE!");
        } catch (\Exception $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN DE CONTACTOS: " . $e->getMessage());
            $this->error("No se realizó ningún cambio. Revisa el error y los logs para más detalles.");
            Log::error('Error en migración de contactos legacy: ' . $e->getMessage() . ' en la línea ' . $e->getLine());
            return 1;
        }

        return 0;
    }

    /**
     * Migra todos los contactos polimórficos (Branch y Supplier).
     */
    private function migrateContacts($oldDb, $newDb)
    {
        // Incluimos Company y CompanyBranch, ya que sus contactos migran a Branch
        $old_contacts = $oldDb->table('contacts')
                              ->whereIn('contactable_type', ['App\Models\Company', 'App\Models\CompanyBranch', 'App\Models\Supplier'])
                              ->orderBy('contactable_type')
                              ->orderBy('contactable_id')
                              ->get();

        if ($old_contacts->isEmpty()) {
            $this->warn("\nNo se encontraron contactos de sucursales o proveedores para migrar.");
            return;
        }

        $this->info("\nMigrando " . $old_contacts->count() . " contactos...");
        $progressBar = $this->output->createProgressBar($old_contacts->count());
        
        $processedContactables = []; // Para asignar 'is_primary' al primer contacto de cada entidad

        foreach ($old_contacts as $contact) {
            $newContactableType = null;
            $newParentId = null;

            // Mapear el tipo y buscar el ID del padre en la nueva DB
            switch ($contact->contactable_type) {
                case 'App\Models\Company':
                case 'App\Models\CompanyBranch':
                    $newContactableType = 'App\Models\Branch';
                    $oldParentName = null;

                    if ($contact->contactable_type === 'App\Models\Company') {
                        $oldParent = $oldDb->table('companies')->where('id', $contact->contactable_id)->first();
                        $oldParentName = $oldParent->business_name ?? null;
                    } else { // CompanyBranch
                        $oldParent = $oldDb->table('company_branches')->where('id', $contact->contactable_id)->first();
                        $oldParentName = $oldParent->name ?? null;
                    }
                    
                    if ($oldParentName) {
                        // Usamos un cache para no consultar el mismo nombre de branch repetidamente
                        if (isset($this->branchNameCache[$oldParentName])) {
                            $newParentId = $this->branchNameCache[$oldParentName];
                        } else {
                            $newBranch = $newDb->table('branches')->where('name', $oldParentName)->first();
                            if ($newBranch) {
                                $newParentId = $newBranch->id;
                                $this->branchNameCache[$oldParentName] = $newParentId; // Guardar en cache
                            }
                        }
                    }

                    if (!$newParentId) {
                         $this->warn("\nAdvertencia: No se encontró un cliente (branch) con el nombre '{$oldParentName}'. Saltando contacto '{$contact->name}' (ID: {$contact->id}).");
                         $progressBar->advance();
                         continue 2;
                    }
                    break;
                    
                case 'App\Models\Supplier':
                    $newContactableType = 'App\Models\Supplier';
                    // Para proveedores, el ID debería coincidir. Lo verificamos.
                    $parentExists = $newDb->table('suppliers')->where('id', $contact->contactable_id)->exists();
                     if ($parentExists) {
                        $newParentId = $contact->contactable_id;
                    } else {
                        $this->warn("\nAdvertencia: El proveedor con ID antiguo {$contact->contactable_id} no existe. Saltando contacto '{$contact->name}' (ID: {$contact->id}).");
                        $progressBar->advance();
                        continue 2;
                    }
                    break;
            }
            
            // Determinar si es el contacto principal para la NUEVA entidad padre
            $contactableKey = $newContactableType . '-' . $newParentId;
            $isPrimary = !in_array($contactableKey, $processedContactables);
            if ($isPrimary) {
                $processedContactables[] = $contactableKey;
            }

            $newDb->table('contacts')->insert([
                'id' => $contact->id, // Mantener el ID original del contacto
                'contactable_type' => $newContactableType,
                'contactable_id' => $newParentId, // Usar el NUEVO ID encontrado por nombre
                'name' => $contact->name,
                'charge' => $contact->charge,
                'birthdate' => $this->formatBirthdate($contact->birthdate_day, $contact->birthdate_month),
                'is_primary' => $isPrimary,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ]);

            // Migrar detalles de contacto (emails y teléfonos)
            $this->migrateContactDetails($newDb, $contact->id, $contact);

            $progressBar->advance();
        }
        $progressBar->finish();
    }
    
    /**
     * Inserta los detalles (email, teléfono) de un contacto en la tabla `contact_details`.
     */
    private function migrateContactDetails($newDb, $newContactId, $oldContact)
    {
        // Insertar detalle de email primario
        if (!empty($oldContact->email)) {
            $newDb->table('contact_details')->insert([
                'contact_id' => $newContactId,
                'type' => 'Correo',
                'value' => $oldContact->email,
                'is_primary' => true,
            ]);
        }

        // Insertar detalle de teléfono primario
        if (!empty($oldContact->phone)) {
            $newDb->table('contact_details')->insert([
                'contact_id' => $newContactId,
                'type' => 'Teléfono',
                'value' => $oldContact->phone,
                'is_primary' => true,
            ]);
        }

        // Insertar emails adicionales
        if (!empty($oldContact->additional_emails)) {
            $additionalEmails = json_decode($oldContact->additional_emails, true);
            if (is_array($additionalEmails)) {
                foreach ($additionalEmails as $email) {
                    $emailValue = is_array($email) ? ($email['email'] ?? null) : $email;
                    if ($emailValue && filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                        $newDb->table('contact_details')->insert([
                            'contact_id' => $newContactId,
                            'type' => 'Correo',
                            'value' => $emailValue,
                            'is_primary' => false,
                        ]);
                    }
                }
            }
        }

        // Insertar teléfonos adicionales
        if (!empty($oldContact->additional_phones)) {
            $additionalPhones = json_decode($oldContact->additional_phones, true);
            if (is_array($additionalPhones)) {
                foreach ($additionalPhones as $phone) {
                    $phoneValue = is_array($phone) ? ($phone['phone'] ?? null) : $phone;
                    if ($phoneValue) {
                        $newDb->table('contact_details')->insert([
                            'contact_id' => $newContactId,
                            'type' => 'Teléfono',
                            'value' => $phoneValue,
                            'is_primary' => false,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Formatea la fecha de nacimiento usando un año genérico (2000).
     */
    private function formatBirthdate($day, $month): ?string
    {
        if (!$day || !$month || $day > 31 || $month > 12) {
            return null;
        }

        try {
            // Usamos un año bisiesto como 2000 para aceptar 29 de Febrero
            return Carbon::create(2000, $month, $day)->format('Y-m-d');
        } catch (\Exception $e) {
            // En caso de fecha inválida (ej. 31 de Febrero)
            return null;
        }
    }
}

