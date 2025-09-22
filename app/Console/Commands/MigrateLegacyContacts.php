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
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los contactos desde la base de datos antigua a las nuevas tablas (contacts, contact_details y supplier_contacts).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de contactos...");
        $this->warn("IMPORTANTE: Asegúrate de haber ejecutado primero la migración de clientes (sucursales) y proveedores, ya que este script depende de sus IDs.");

        if ($this->confirm('¿Deseas eliminar los datos de las tablas "contacts", "contact_details" y "supplier_contacts" antes de empezar?')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('contact_details')->truncate();
            DB::table('contacts')->truncate();
            DB::table('supplier_contacts')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->warn('Las tablas de contactos han sido limpiadas.');
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->migrateBranchContacts($oldDb, $newDb);
                $this->migrateSupplierContacts($oldDb, $newDb);
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
     * Migra los contactos asociados a Sucursales (CompanyBranch).
     */
    private function migrateBranchContacts($oldDb, $newDb)
    {
        $contactable_type = 'App\Models\CompanyBranch';
        
        $old_contacts = $oldDb->table('contacts')
                              ->where('contactable_type', $contactable_type)
                              ->orderBy('contactable_id')
                              ->get();

        if ($old_contacts->isEmpty()) {
            $this->warn("\nNo se encontraron contactos de sucursales (CompanyBranch) para migrar.");
            return;
        }

        $this->info("\nMigrando " . $old_contacts->count() . " contactos de sucursales...");
        $progressBar = $this->output->createProgressBar($old_contacts->count());
        
        $processedBranches = []; // Para asignar el 'is_primary' al primer contacto de cada sucursal

        foreach ($old_contacts as $contact) {
            // El contactable_id corresponde al id de la sucursal en la nueva tabla 'branches'
            $branchId = $contact->contactable_id;
            
            // Verificamos si la sucursal existe en la nueva BD
            if (!$newDb->table('branches')->where('id', $branchId)->exists()) {
                 $this->warn("\nAdvertencia: La sucursal (branch) con ID antiguo {$branchId} no existe en la nueva base de datos. Saltando contacto '{$contact->name}'.");
                 $progressBar->advance();
                 continue;
            }

            $isPrimary = !in_array($branchId, $processedBranches);
            if ($isPrimary) {
                $processedBranches[] = $branchId;
            }

            $newContactId = $newDb->table('contacts')->insertGetId([
                'branch_id' => $branchId,
                'name' => $contact->name,
                'charge' => $contact->charge,
                'birthdate' => $this->formatBirthdate($contact->birthdate_day, $contact->birthdate_month),
                'is_primary' => $isPrimary,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ]);

            // Migrar detalles de contacto (emails y teléfonos)
            $this->migrateContactDetails($newDb, $newContactId, $contact);

            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * Migra los contactos asociados a Proveedores (Suppliers).
     */
    private function migrateSupplierContacts($oldDb, $newDb)
    {
        $old_contacts = $oldDb->table('contacts')
                              ->where('contactable_type', 'App\Models\Supplier')
                              ->orderBy('contactable_id')
                              ->get();
        
        if ($old_contacts->isEmpty()) {
            $this->warn("\nNo se encontraron contactos de proveedores para migrar.");
            return;
        }

        $this->info("\nMigrando " . $old_contacts->count() . " contactos de proveedores...");
        $progressBar = $this->output->createProgressBar($old_contacts->count());

        $processedSuppliers = []; // Para asignar el 'is_primary' al primer contacto de cada proveedor

        foreach ($old_contacts as $contact) {
            $supplierId = $contact->contactable_id;
            
            // Verificamos si el proveedor existe en la nueva BD.
            if (!$newDb->table('suppliers')->where('id', $supplierId)->exists()) {
                 $this->warn("\nAdvertencia: El proveedor con ID antiguo {$supplierId} no existe en la nueva base de datos. Saltando contacto '{$contact->name}'.");
                 $progressBar->advance();
                 continue;
            }

            $isPrimary = !in_array($supplierId, $processedSuppliers);
            if ($isPrimary) {
                $processedSuppliers[] = $supplierId;
            }

            $newDb->table('supplier_contacts')->insert([
                'supplier_id' => $supplierId,
                'name' => $contact->name,
                'position' => $contact->charge, // Mapeamos 'charge' a 'position'
                'email' => $contact->email,
                'phone' => $contact->phone,
                'is_primary' => $isPrimary,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ]);
            
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

