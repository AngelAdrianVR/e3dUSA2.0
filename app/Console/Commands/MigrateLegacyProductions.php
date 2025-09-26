<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyProductions extends Command
{
    /**
     * The name and signature of the console command.
     * N°13 Todo correcto!
     * @var string
     */
    protected $signature = 'app:migrate-legacy-productions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de producción desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Producción...");

        // Listas para guardar advertencias
        $notFoundSaleProducts = [];
        $missingUsers = [];

        $tablesToTruncate = ['productions', 'production_tasks'];
        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "' . implode('" y "', $tablesToTruncate) . '" antes de empezar?', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($tablesToTruncate as $table) {
                    DB::table($table)->truncate();
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas de destino han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // --- Cargar mapeos de productos ---
            $this->info('Cargando mapeos de productos...');
            $oldCatalogProducts = $oldDb->table('catalog_products')->pluck('name', 'id');
            $oldCompanyProductToCatalog = $oldDb->table('catalog_product_company')->pluck('catalog_product_id', 'id');
            $newProducts = $newDb->table('products')->pluck('id', 'name');
            $this->info('Mapeos cargados.');

            $newDb->transaction(function () use ($oldDb, $newDb, $oldCatalogProducts, $oldCompanyProductToCatalog, $newProducts, &$notFoundSaleProducts, &$missingUsers) {

                $this->line('');
                $this->info('Migrando registros de producción...');
                
                $old_productions = $oldDb->table('productions')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_productions->count());

                foreach ($old_productions as $oldProduction) {
                    // --- 1. Encontrar el producto de venta correspondiente en la nueva BD ---
                    $old_cpcs = $oldDb->table('catalog_product_company_sale')->find($oldProduction->catalog_product_company_sale_id);
                    if (!$old_cpcs) {
                        $progressBar->advance();
                        continue; // Si no existe el producto vendido, no se puede migrar
                    }

                    // Encontrar el nombre del producto antiguo
                    $oldCatalogProductId = $oldCompanyProductToCatalog->get($old_cpcs->catalog_product_company_id);
                    $oldProductName = $oldCatalogProductId ? $oldCatalogProducts->get($oldCatalogProductId) : null;
                    
                    // Encontrar el ID del producto en la nueva BD
                    $newProductId = $oldProductName ? $newProducts->get($oldProductName) : null;

                    // Encontrar el registro en `sale_products` de la nueva BD
                    $newSaleProduct = null;
                    if ($newProductId) {
                         $newSaleProduct = $newDb->table('sale_products')
                            ->where('sale_id', $old_cpcs->sale_id)
                            ->where('product_id', $newProductId)
                            ->first();
                    }

                    if (!$newSaleProduct) {
                        $notFoundSaleProducts[] = "Producción antigua ID {$oldProduction->id} -> Producto '{$oldProductName}' en Venta ID {$old_cpcs->sale_id}";
                        $progressBar->advance();
                        continue;
                    }

                    // --- 2. Verificar existencia de usuarios ---
                    if (!$newDb->table('users')->where('id', $oldProduction->user_id)->exists() || !$newDb->table('users')->where('id', $oldProduction->operator_id)->exists()) {
                        $missingUsers[] = "Producción antigua ID {$oldProduction->id} (Creador: {$oldProduction->user_id}, Operador: {$oldProduction->operator_id})";
                        $progressBar->advance();
                        continue;
                    }

                    // --- 3. Crear el registro principal en `productions` ---
                    $productionStatus = $this->mapProductionStatus($oldProduction);
                    $newProductionId = $newDb->table('productions')->insertGetId([
                        'sale_product_id' => $newSaleProduct->id,
                        'created_by_user_id' => $oldProduction->user_id,
                        'quantity_to_produce' => $old_cpcs->quantity,
                        'status' => $productionStatus,
                        'started_at' => $productionStatus !== 'Pendiente' ? $oldProduction->started_at : null,
                        'finished_at' => $productionStatus === 'Terminada' ? $oldProduction->finished_at : null,
                        'good_units' => $oldProduction->good_units ?? 0,
                        'scrap' => $oldProduction->scrap ?? 0,
                        'scrap_reason' => $oldProduction->scrap_reason,
                        'created_at' => $oldProduction->created_at,
                        'updated_at' => $oldProduction->updated_at,
                    ]);

                    // --- 4. Crear la tarea en `production_tasks` ---
                    $newDb->table('production_tasks')->insert([
                        'production_id' => $newProductionId,
                        'operator_id' => $oldProduction->operator_id,
                        'name' => $oldProduction->tasks ?: 'Producción General',
                        'estimated_time_minutes' => ($oldProduction->estimated_time_hours * 60) + $oldProduction->estimated_time_minutes,
                        'status' => $this->mapTaskStatus($oldProduction),
                        'started_at' => $oldProduction->started_at,
                        'finished_at' => $oldProduction->finished_at,
                        'created_at' => $oldProduction->created_at,
                        'updated_at' => $oldProduction->updated_at,
                    ]);

                    $progressBar->advance();
                }

                $progressBar->finish();
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE PRODUCCIÓN COMPLETADA EXITOSAMENTE!");

            if (!empty($notFoundSaleProducts)) {
                $this->warn("\nADVERTENCIA: No se migraron las siguientes producciones porque no se encontró el producto vendido correspondiente en la nueva BD:");
                foreach (array_unique($notFoundSaleProducts) as $message) {
                    $this->line("- " . $message);
                }
            }
             if (!empty($missingUsers)) {
                $this->warn("\nADVERTENCIA: No se migraron las siguientes producciones porque el usuario creador u operador no existe en la nueva BD:");
                foreach (array_unique($missingUsers) as $message) {
                    $this->line("- " . $message);
                }
            }

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("Línea: " . $e->getLine() . " en " . $e->getFile());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de producción: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function mapTaskStatus(object $oldProduction): string
    {
        if ($oldProduction->started_at && $oldProduction->finished_at) {
            return 'Terminada';
        }
        if ($oldProduction->started_at) {
            return 'En Proceso';
        }
        return 'Pendiente';
    }

    private function mapProductionStatus(object $oldProduction): string
    {
        // Dado que una producción antigua equivale a una tarea nueva, el estado es el mismo.
        if ($oldProduction->started_at && $oldProduction->finished_at) {
            return 'Terminada';
        }
        if ($oldProduction->started_at) {
            return 'En Proceso';
        }
        return 'Pendiente';
    }
}
