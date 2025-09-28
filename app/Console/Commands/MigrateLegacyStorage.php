<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Product; // Importa tu nuevo modelo Product. hecho.
use Throwable;

class MigrateLegacyStorage extends Command
{
    /**
     * The name and signature of the console command.
     * En cualquier orden despues de los productos.
     * @var string
     */
    protected $signature = 'app:migrate-legacy-storage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra el inventario de la tabla polimórfica "storages" desde la base de datos antigua a la nueva.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la migración de inventario (storages)...");

        // Listas para guardar items no encontrados
        $notFoundProducts = [];
        $storageNotPreExisting = [];

        try {
            // Define las conexiones a las bases de datos
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // Usa una transacción para asegurar la integridad de los datos.
            // Si algo falla, se revertirá toda la operación.
            $newDb->transaction(function () use ($oldDb, $newDb, &$notFoundProducts, &$storageNotPreExisting) {
                
                 $this->line('');
                 $this->info('Leyendo inventario antiguo...');

                $oldStorages = $oldDb->table('storages')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($oldStorages->count());
                
                $this->info("Se encontraron {$oldStorages->count()} registros de inventario para migrar.");
                $progressBar->start();

                foreach ($oldStorages as $oldStorage) {
                    // 1. Obtener el producto o materia prima de la BD antigua
                    $oldItem = $this->getLegacyItem($oldDb, $oldStorage->storageable_type, $oldStorage->storageable_id);

                    if (!$oldItem) {
                        $this->warn("\nNo se encontró el item antiguo con ID: {$oldStorage->storageable_id} y tipo: {$oldStorage->storageable_type}. Saltando...");
                        $progressBar->advance();
                        continue;
                    }
                    
                    // 2. Buscar el producto correspondiente en la nueva BD por nombre
                    $newItem = $newDb->table('products')->where('name', $oldItem->name)->first();

                    if (!$newItem) {
                        // Si no se encuentra, lo guardamos para reportarlo al final
                        $notFoundProducts[] = $oldItem->name;
                        $progressBar->advance();
                        continue;
                    }

                    // 3. Preparar los datos y actualizar el registro en la nueva tabla 'storages'
                    $quantity = $oldStorage->quantity > 0 ? $oldStorage->quantity : 0; // Asegura que la cantidad no sea negativa

                    // Buscar el registro de storage existente para actualizarlo
                    $existingStorage = $newDb->table('storages')
                        ->where('storable_id', $newItem->id)
                        ->where('storable_type', Product::class)
                        ->first();

                    if ($existingStorage) {
                        // Si existe, actualiza el registro
                        $newDb->table('storages')
                            ->where('id', $existingStorage->id)
                            ->update([
                                'quantity' => $quantity,
                                'location' => $oldStorage->location,
                                'updated_at' => now(),
                            ]);
                    } else {
                        // Si no existe, no se crea, solo se reporta al final
                        $storageNotPreExisting[] = $newItem->name;
                    }

                    $progressBar->advance();
                }

                $progressBar->finish();
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE INVENTARIO COMPLETADA EXITOSAMENTE!");

            // 4. Reportar si hubo productos que no se encontraron en la nueva BD
            if (!empty($notFoundProducts)) {
                $this->warn("\nADVERTENCIA: No se pudo migrar el inventario para los siguientes productos porque no se encontraron en la nueva base de datos:");
                foreach (array_unique($notFoundProducts) as $productName) {
                    $this->line("- " . $productName);
                }
            }
            
            // 5. Reportar si para algún producto no se encontró su registro de storage
            if (!empty($storageNotPreExisting)) {
                $this->warn("\nADVERTENCIA: No se actualizó el inventario para los siguientes productos porque no se encontró su registro de 'storage' preexistente:");
                foreach (array_unique($storageNotPreExisting) as $productName) {
                    $this->line("- " . $productName);
                }
            }

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de storages: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1; // Termina con código de error
        }

        return 0; // Termina exitosamente
    }

    /**
     * Obtiene el item (producto o materia prima) de la base de datos antigua.
     *
     * @param \Illuminate\Database\Connection $oldDb
     * @param string $type
     * @param int $id
     * @return object|null
     */
    private function getLegacyItem($oldDb, string $type, int $id): ?object
    {
        // Mapea el 'storageable_type' de la BD antigua al nombre de la tabla correspondiente.
        // ¡IMPORTANTE! Ajusta estos valores si los namespace o nombres de tus modelos antiguos son diferentes.
        $tableName = match ($type) {
            'App\Models\CatalogProduct', 'App\CatalogProduct' => 'catalog_products', // Asumiendo el nombre de la tabla
            'App\Models\RawMaterial', 'App\RawMaterial' => 'raw_materials',         // Asumiendo el nombre de la tabla
            default => null,
        };

        if (!$tableName) {
            return null;
        }

        return $oldDb->table($tableName)->find($id);
    }
}

