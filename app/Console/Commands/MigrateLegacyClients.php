<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MigrateLegacyClients extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates companies, branches, prospects and their contacts from the old database to the new structure.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de clientes, sucursales y prospectos...");

        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "branches", "contacts" y "contact_details" antes de empezar? Esto es recomendado para una migración limpia.')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('contact_details')->truncate();
            DB::table('contacts')->truncate();
            DB::table('branches')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->warn('Las tablas "branches", "contacts" y "contact_details" han sido limpiadas.');
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->migrateCompanies($oldDb, $newDb);
                $this->migrateCompanyBranches($oldDb, $newDb);
                $this->migrateProspects($oldDb, $newDb);
            });

            $this->info("\n\n¡MIGRACIÓN COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los clientes, sucursales, prospectos y sus contactos han sido transferidos.");
        } catch (\Exception $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo. Detalles en el log.");
            Log::error('Error en migración de clientes legacy: ' . $e->getMessage() . ' en la línea ' . $e->getLine());
            return 1;
        }

        return 0;
    }

    /**
     * Migra las compañías matrices.
     */
    private function migrateCompanies($oldDb, $newDb)
    {
        $old_companies = $oldDb->table('companies')->orderBy('id')->get();
        if ($old_companies->isEmpty()) {
            $this->warn("No se encontraron compañías matrices en la base de datos antigua.");
            return;
        }

        $this->info("\nMigrando " . $old_companies->count() . " compañías matrices...");
        $progressBar = $this->output->createProgressBar($old_companies->count());

        foreach ($old_companies as $company) {
            $rfc = $company->rfc;
            if (strlen($rfc) > 20) {
                $truncatedRfc = substr($rfc, 0, 20);
                $this->warn("\nAdvertencia: El RFC '{$rfc}' (company_id: {$company->id}) excede los 20 caracteres. Será truncado a '{$truncatedRfc}'. Se recomienda ampliar la columna 'rfc' en la tabla 'branches'.");
                $rfc = $truncatedRfc;
            }

            $newBranchId = $newDb->table('branches')->insertGetId([
                'id' => $company->id, // Mantenemos el ID original para facilitar la relación con sucursales
                'name' => $company->business_name,
                'password' => Hash::make('e3d-'. str_pad($company->id, 4, '0', STR_PAD_LEFT)), // Contraseña provisional
                'rfc' => $rfc,
                'address' => $company->fiscal_address,
                'post_code' => $company->post_code,
                'status' => 'Cliente',
                'parent_branch_id' => null, // Es una matriz
                'account_manager_id' => $company->seller_id,
                'days_to_reactive' => 60, // Valor por defecto nuevo
                'sat_method' => null,
                'sat_type' => null,
                'meet_way' => null,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
            ]);

            // Migrar contactos asociados a esta compañía
            $this->migrateContacts($oldDb, $newDb, 'App\Models\Company', $company->id, $newBranchId);

            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * Migra las sucursales de las compañías.
     */
    private function migrateCompanyBranches($oldDb, $newDb)
    {
        $old_branches = $oldDb->table('company_branches')->orderBy('id')->get();
        if ($old_branches->isEmpty()) {
            $this->warn("\nNo se encontraron sucursales en la base de datos antigua.");
            return;
        }

        $this->info("\nMigrando " . $old_branches->count() . " sucursales...");
        $progressBar = $this->output->createProgressBar($old_branches->count());

        foreach ($old_branches as $branch) {
             // La nueva estructura no tiene ID autoincremental para las sucursales, usamos el de la BD
            $newBranchId = $newDb->table('branches')->insertGetId([
                'name' => $branch->name,
                'password' => $branch->password,
                'rfc' => null, // El RFC está en la matriz
                'address' => $branch->address,
                'post_code' => $branch->post_code,
                'status' => 'Cliente',
                'parent_branch_id' => $branch->company_id, // El ID de la matriz se mantuvo
                'account_manager_id' => $oldDb->table('companies')->where('id', $branch->company_id)->value('seller_id'), // Heredamos el vendedor
                'days_to_reactive' => $branch->days_to_reactivate ?? 60,
                'sat_method' => $branch->sat_method,
                'sat_type' => $branch->sat_type,
                'meet_way' => $branch->meet_way,
                'created_at' => $branch->created_at,
                'updated_at' => $branch->updated_at,
            ]);

            // Migrar contactos asociados a esta sucursal
            $this->migrateContacts($oldDb, $newDb, 'App\Models\CompanyBranch', $branch->id, $newBranchId);

            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * Migra los prospectos.
     */
    private function migrateProspects($oldDb, $newDb)
    {
        $old_prospects = $oldDb->table('prospects')->orderBy('id')->get();
        if ($old_prospects->isEmpty()) {
            $this->warn("\nNo se encontraron prospectos en la base de datos antigua.");
            return;
        }

        $this->info("\nMigrando " . $old_prospects->count() . " prospectos...");
        $progressBar = $this->output->createProgressBar($old_prospects->count());

        foreach ($old_prospects as $prospect) {
            $newBranchId = $newDb->table('branches')->insertGetId([
                'name' => $prospect->name,
                'password' => Hash::make('prospect-'. str_pad($prospect->id, 4, '0', STR_PAD_LEFT)), // Contraseña provisional
                'rfc' => null,
                'address' => $prospect->address,
                'post_code' => null,
                'status' => 'Prospecto',
                'parent_branch_id' => null,
                'account_manager_id' => $prospect->seller_id,
                'days_to_reactive' => 60,
                'sat_method' => null,
                'sat_type' => null,
                'meet_way' => null,
                'created_at' => $prospect->created_at,
                'updated_at' => $prospect->updated_at,
            ]);

            // El prospecto antiguo tenía un solo contacto directo, lo creamos
            if (!empty($prospect->contact_name)) {
                $newContactId = $newDb->table('contacts')->insertGetId([
                    'branch_id' => $newBranchId,
                    'name' => $prospect->contact_name,
                    'charge' => $prospect->contact_charge,
                    'birthdate' => null, // No disponible en prospectos
                    'is_primary' => true, // Es el único contacto
                    'created_at' => $prospect->created_at,
                    'updated_at' => $prospect->updated_at,
                ]);

                // Insertar detalles del contacto
                if (!empty($prospect->contact_email)) {
                    $newDb->table('contact_details')->insert([
                        'contact_id' => $newContactId,
                        'type' => 'Correo',
                        'value' => $prospect->contact_email,
                        'is_primary' => true,
                    ]);
                }
                if (!empty($prospect->contact_phone)) {
                    $phone_value = $prospect->contact_phone;
                    if (!empty($prospect->contact_phone_extension)) {
                        $phone_value .= ' Ext. ' . $prospect->contact_phone_extension;
                    }
                    $newDb->table('contact_details')->insert([
                        'contact_id' => $newContactId,
                        'type' => 'Teléfono',
                        'value' => $phone_value,
                        'is_primary' => true,
                    ]);
                }
                if (!empty($prospect->contact_whatsapp)) {
                    $newDb->table('contact_details')->insert([
                        'contact_id' => $newContactId,
                        'type' => 'Whatsapp',
                        'value' => $prospect->contact_whatsapp,
                        'is_primary' => false,
                    ]);
                }
            }
            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * Migra los contactos polimórficos de la base de datos antigua.
     */
    private function migrateContacts($oldDb, $newDb, $contactableType, $contactableId, $newBranchId)
    {
        $old_contacts = $oldDb->table('contacts')
            ->where('contactable_type', $contactableType)
            ->where('contactable_id', $contactableId)
            ->get();

        $isFirstContact = true;
        foreach ($old_contacts as $contact) {
            $newContactId = $newDb->table('contacts')->insertGetId([
                'branch_id' => $newBranchId,
                'name' => $contact->name,
                'charge' => $contact->charge,
                'birthdate' => $this->formatBirthdate($contact->birthdate_day, $contact->birthdate_month),
                'is_primary' => $isFirstContact,
                'created_at' => $contact->created_at,
                'updated_at' => $contact->updated_at,
            ]);
            $isFirstContact = false;

            // Insertar detalle de email primario
            if (!empty($contact->email)) {
                $newDb->table('contact_details')->insert([
                    'contact_id' => $newContactId,
                    'type' => 'Correo',
                    'value' => $contact->email,
                    'is_primary' => true,
                ]);
            }

            // Insertar detalle de teléfono primario
            if (!empty($contact->phone)) {
                $newDb->table('contact_details')->insert([
                    'contact_id' => $newContactId,
                    'type' => 'Teléfono',
                    'value' => $contact->phone,
                    'is_primary' => true,
                ]);
            }

            // Insertar emails adicionales
            if (!empty($contact->additional_emails)) {
                $additionalEmails = json_decode($contact->additional_emails, true);
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
            if (!empty($contact->additional_phones)) {
                $additionalPhones = json_decode($contact->additional_phones, true);
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
    }
    
    /**
     * Formatea la fecha de nacimiento.
     * La DB antigua guardaba día y mes por separado. El año no existe.
     * Usamos un año genérico (2000) para poder guardar una fecha válida.
     *
     * @param int|null $day
     * @param int|null $month
     * @return string|null
     */
    private function formatBirthdate($day, $month): ?string
    {
        if (!$day || !$month) {
            return null;
        }

        try {
            // Usamos el año 2000 como placeholder ya que no se almacenaba
            return Carbon::create(2000, $month, $day)->format('Y-m-d');
        } catch (\Exception $e) {
            // En caso de fecha inválida (ej. 31 de Febrero)
            return null;
        }
    }
}

