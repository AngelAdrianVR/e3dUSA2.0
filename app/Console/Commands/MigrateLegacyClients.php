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
     * N°4
     * @var string
     */
    protected $signature = 'app:migrate-legacy-clients';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates companies, branches, and prospects from the old database to the new structure.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de clientes, sucursales y prospectos...");

        if ($this->confirm('¿Deseas eliminar TODOS los datos de la tabla "branches" antes de empezar? Esto es recomendado para una migración limpia.')) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table('branches')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->warn('La tabla "branches" ha sido limpiada.');
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->migrateCompanies($oldDb, $newDb);
                $this->migrateCompanyBranches($oldDb, $newDb);
                $this->migrateProspects($oldDb, $newDb); // Los prospectos se migran al final
            });

            $this->info("\n\n¡MIGRACIÓN COMPLETADA EXITOSAMENTE!");
            $this->info("Todos los clientes, sucursales y prospectos han sido transferidos.");
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

            $newDb->table('branches')->insert([
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
            $newDb->table('branches')->insert([
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
            $newDb->table('branches')->insert([
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

            $progressBar->advance();
        }
        $progressBar->finish();
    }
}
