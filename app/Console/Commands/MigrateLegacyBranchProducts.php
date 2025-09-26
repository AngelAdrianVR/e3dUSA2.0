<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyBranchProducts extends Command
{
    /**
     * The name and signature of the console command.
     * N°6. existen sucursales que se llaman igual que la matriz y a esas sucursales les asigna los productos (esta correcto porque así esta en el antiguo, pero no
     * es lo ideal)
     * @var string
     */
    protected $signature = 'app:migrate-legacy-branch-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los productos asignados a clientes y su historial de precios desde la BD antigua.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de productos por cliente y precios especiales...");

        if ($this->confirm('¿Deseas eliminar TODOS los datos de "branch_product" y "branch_price_history" antes de empezar?', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('branch_product')->truncate();
                DB::table('branch_price_history')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas "branch_product" y "branch_price_history" han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->line('Cacheando datos necesarios de la nueva base de datos...');
                $newBranches = $newDb->table('branches')->pluck('id', 'name');
                $newProducts = $newDb->table('products')->pluck('id', 'name');
                $this->info('Datos cacheados con éxito.');

                $this->line('');
                $this->info('Migrando relaciones cliente-producto y su historial de precios...');
                
                $oldRelations = $oldDb->table('catalog_product_company as cpc')
                    ->join('companies', 'companies.id', '=', 'cpc.company_id')
                    ->join('catalog_products', 'catalog_products.id', '=', 'cpc.catalog_product_id')
                    ->select(
                        'companies.business_name as branch_name',
                        'catalog_products.name as product_name',
                        'cpc.new_price', 'cpc.new_currency', 'cpc.new_date',
                        'cpc.old_price', 'cpc.old_currency', 'cpc.old_date',
                        'cpc.oldest_price', 'cpc.oldest_currency', 'cpc.oldest_date',
                        'cpc.created_at', 'cpc.updated_at'
                    )
                    ->orderBy('cpc.id')
                    ->get();
                
                $progressBar = $this->output->createProgressBar($oldRelations->count());

                foreach ($oldRelations as $relation) {
                    $newBranchId = $newBranches->get($relation->branch_name);
                    $newProductId = $newProducts->get($relation->product_name);

                    if (!$newBranchId) {
                        $this->warn("\nAdvertencia: No se encontró la sucursal '{$relation->branch_name}' en la nueva BD. Saltando relación de producto.");
                        $progressBar->advance();
                        continue;
                    }

                    if (!$newProductId) {
                        $this->warn("\nAdvertencia: No se encontró el producto '{$relation->product_name}' en la nueva BD. Saltando relación de producto.");
                        $progressBar->advance();
                        continue;
                    }

                    // Insertar la relación en la tabla pivote
                    $newDb->table('branch_product')->updateOrInsert(
                        ['branch_id' => $newBranchId, 'product_id' => $newProductId],
                        ['created_at' => $relation->created_at, 'updated_at' => $relation->updated_at]
                    );

                    // Insertar el historial de precios
                    $this->migratePrices($newDb, $newBranchId, $newProductId, $relation);
                    
                    $progressBar->advance();
                }

                $progressBar->finish();
                $this->info("\n -> Relaciones y precios migrados correctamente.");
            });

            $this->line('');
            $this->info("¡MIGRACIÓN COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
            Log::error('Error en migración de branch-products: ' . $e->getFile() . ' L:' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Migra los 3 precios (new, old, oldest) a la nueva tabla de historial.
     */
    private function migratePrices($newDb, $branchId, $productId, $relation): void
    {
        // Precio Nuevo (actual)
        if ($relation->new_price > 0 && $relation->new_date) {
            $newDb->table('branch_price_history')->insert([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'price' => $relation->new_price,
                'currency' => $relation->new_currency ?? 'MXN',
                'valid_from' => $relation->new_date,
                'valid_to' => null, // El precio más nuevo no tiene fecha de fin
                'created_at' => $relation->created_at,
                'updated_at' => $relation->updated_at,
            ]);
        }

        // Precio Antiguo
        if ($relation->old_price > 0 && $relation->old_date) {
            $newDb->table('branch_price_history')->insert([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'price' => $relation->old_price,
                'currency' => $relation->old_currency ?? 'MXN',
                'valid_from' => $relation->old_date,
                'valid_to' => $relation->new_date, // La vigencia termina cuando empieza el 'nuevo'
                'created_at' => $relation->created_at,
                'updated_at' => $relation->updated_at,
            ]);
        }

        // Precio Más Antiguo
        if ($relation->oldest_price > 0 && $relation->oldest_date) {
            $newDb->table('branch_price_history')->insert([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'price' => $relation->oldest_price,
                'currency' => $relation->oldest_currency ?? 'MXN',
                'valid_from' => $relation->oldest_date,
                'valid_to' => $relation->old_date, // La vigencia termina cuando empieza el 'antiguo'
                'created_at' => $relation->created_at,
                'updated_at' => $relation->updated_at,
            ]);
        }
    }
}
