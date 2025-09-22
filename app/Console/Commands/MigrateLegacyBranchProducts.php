<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Illuminate\Support\Collection;

class MigrateLegacyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-branch-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra productos asignados a clientes, precios especiales e inventario desde la BD antigua.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Productos, Precios y Almacenes...");

        // Confirmación para limpiar las tablas nuevas
        if ($this->confirm('¿Deseas eliminar TODOS los datos de "branch_product" y "branch_price_history" antes de empezar? Los datos de "storages" solo se actualizarán.', true)) {
            try {
                DB::statement('SET FOREIGN_key_CHECKS=0;');
                DB::table('branch_product')->truncate();
                DB::table('branch_price_history')->truncate();
                // Ya no se trunca la tabla storages
                DB::statement('SET FOREIGN_key_CHECKS=1;');
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
                // --- CACHING ---
                // Para evitar consultas N+1, cargamos los datos necesarios de la nueva BD en memoria.
                $this->line('Cacheando datos de la nueva base de datos...');
                $newBranches = $newDb->table('branches')->pluck('id', 'name');
                $newProducts = $newDb->table('products')->pluck('id', 'name');
                $this->info('Datos cacheados con éxito.');

                // --- 1. Migrar Relaciones Cliente-Producto y Precios ---
                $this->line('');
                $this->info('Migrando relaciones y precios por cliente...');

                // Asumimos que la tabla 'companies' en la BD antigua contiene el nombre del cliente/sucursal
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
                        $this->warn("\nAdvertencia: No se encontró la sucursal '{$relation->branch_name}' en la nueva BD. Saltando...");
                        continue;
                    }

                    if (!$newProductId) {
                        $this->warn("\nAdvertencia: No se encontró el producto '{$relation->product_name}' en la nueva BD. Saltando...");
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
                $this->info(' -> Relaciones y precios migrados.');

                // --- 2. Migrar Inventario (Storages) ---
                $this->line('');
                $this->info('Actualizando inventario de productos (storages)...');

                // Cachear productos de la BD antigua para no hacer consultas en el bucle
                $oldCatalogProducts = $oldDb->table('catalog_products')->pluck('name', 'id');
                $oldRawMaterials = $oldDb->table('raw_materials')->pluck('name', 'id');

                // Obtener todos los registros de la tabla de storages antigua
                $oldStorages = $oldDb->table('storages')->get();
                $progressBarStorages = $this->output->createProgressBar($oldStorages->count());

                foreach ($oldStorages as $storage) {
                    $productName = null;

                    // Determinar el nombre del producto basado en el campo 'type' y el 'storable_id'
                    // de la tabla polimórfica antigua
                    if (isset($storage->type) && str_contains($storage->type, 'CatalogProduct')) {
                        $productName = $oldCatalogProducts->get($storage->storable_id);
                    } elseif (isset($storage->type) && str_contains($storage->type, 'RawMaterial')) {
                        $productName = $oldRawMaterials->get($storage->storable_id);
                    }

                    if ($productName) {
                        $newProductId = $newProducts->get($productName);
                        if ($newProductId) {
                            $newDb->table('storages')
                                ->where('storable_id', $newProductId)
                                ->where('storable_type', 'App\Models\Product')
                                ->update([
                                    'quantity' => $storage->quantity,
                                    'location' => $storage->location,
                                    'updated_at' => $storage->updated_at,
                                ]);
                        } else {
                            $this->warn("\nAdvertencia: Producto de inventario '{$productName}' no encontrado en la nueva BD. Saltando.");
                        }
                    } else {
                         $this->warn("\nAdvertencia: No se pudo encontrar el producto original para el storage id {$storage->id}. Saltando...");
                    }
                    $progressBarStorages->advance();
                }

                $progressBarStorages->finish();
                $this->info(' -> Inventario actualizado.');

            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de productos: ' . $e->getFile() . ' L:' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Migra los 3 precios (new, old, oldest) a la nueva tabla de historial.
     *
     * @param \Illuminate\Database\Connection $newDb
     * @param int $branchId
     * @param int $productId
     * @param object $relation
     * @return void
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
                // La vigencia de este precio termina cuando empieza el 'nuevo'
                'valid_to' => $relation->new_date,
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
                 // La vigencia de este precio termina cuando empieza el 'antiguo'
                'valid_to' => $relation->old_date,
                'created_at' => $relation->created_at,
                'updated_at' => $relation->updated_at,
            ]);
        }
    }
}

