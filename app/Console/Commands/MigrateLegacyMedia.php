<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyMedia extends Command
{
    /**
     * The name and signature of the console command.
     * En cualquier orden. Todo bien!
     * @var string
     */
    protected $signature = 'app:migrate-legacy-media';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra los datos de la tabla media (Spatie Media Library) desde la base de datos antigua a la nueva estructura.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de la tabla Media...");

        // Confirmación para limpiar la tabla nueva
        if ($this->confirm('¿Deseas eliminar TODOS los datos de la tabla "media" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_key_CHECKS=0;');
                DB::table('media')->truncate();
                DB::statement('SET FOREIGN_key_CHECKS=1;');
                $this->warn('La tabla "media" ha sido limpiada.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar la tabla media: " . $e->getMessage());
                return 1;
            }
        }

        try {
            // Conexiones a las bases de datos
            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            $newDb->transaction(function () use ($oldDb, $newDb) {
                $this->line('');
                $this->info('Migrando archivos (media)...');

                // --- 1. Preparar mapeo de IDs para Productos (RawMaterial y CatalogProduct) ---
                $this->line('Preparando mapeo de IDs para Productos...');
                $nextProductId = 1;
                $rawMaterialIdMap = [];
                $catalogProductIdMap = [];

                // Se obtienen los IDs únicos de RawMaterial y se les asigna un nuevo ID consecutivo
                $oldDb->table('raw_materials')->orderBy('id')->pluck('id')->each(function ($id) use (&$rawMaterialIdMap, &$nextProductId) {
                    if (!isset($rawMaterialIdMap[$id])) {
                        $rawMaterialIdMap[$id] = $nextProductId++;
                    }
                });
                
                // Se obtienen los IDs únicos de CatalogProduct y se continúa la secuencia
                $oldDb->table('catalog_products')->orderBy('id')->pluck('id')->each(function ($id) use (&$catalogProductIdMap, &$nextProductId) {
                    if (!isset($catalogProductIdMap[$id])) {
                        $catalogProductIdMap[$id] = $nextProductId++;
                    }
                });

                $this->info('Mapeo de IDs para Productos completado.');

                // --- 2. Procesar y migrar la tabla media ---
                $old_media = $oldDb->table('media')->orderBy('id')->get();
                $progressBar = $this->output->createProgressBar($old_media->count());

                foreach ($old_media as $media_item) {
                    $new_model_type = $media_item->model_type;
                    $new_model_id = $media_item->model_id;

                    switch ($media_item->model_type) {
                        case 'App\Models\RawMaterial':
                            $new_model_type = 'App\Models\Product';
                            $new_model_id = $rawMaterialIdMap[$media_item->model_id] ?? null;
                            break;
                        
                        case 'App\Models\CatalogProduct':
                            $new_model_type = 'App\Models\Product';
                            $new_model_id = $catalogProductIdMap[$media_item->model_id] ?? null;
                            break;

                        case 'App\Models\Design':
                            $new_model_type = $media_item->collection_name === 'result'
                                ? 'App\Models\Design'
                                : 'App\Models\DesignOrder';
                            break;
                        
                        case 'App\Models\DesignModification':
                            $new_model_type = 'App\Models\Design';
                            break;

                        case 'App\Models\Manual':
                            $progressBar->advance();
                            continue 2; // Salta al siguiente item del foreach

                        default:
                            // Los demás modelos se mantienen igual
                            break;
                    }

                    // Si no se encontró un ID mapeado, se omite para evitar errores.
                    if ($new_model_id === null) {
                        $this->warn("\nNo se encontró mapeo de ID para el media item #{$media_item->id}, se omite.");
                        Log::warning("No se encontró mapeo de ID para el media item #{$media_item->id} (model_type: {$media_item->model_type}, model_id: {$media_item->model_id})");
                        $progressBar->advance();
                        continue;
                    }

                    $newDb->table('media')->insert([
                        'id' => $media_item->id,
                        'model_type' => $new_model_type,
                        'model_id' => $new_model_id,
                        'uuid' => $media_item->uuid,
                        'collection_name' => $media_item->collection_name,
                        'name' => $media_item->name,
                        'file_name' => $media_item->file_name,
                        'mime_type' => $media_item->mime_type,
                        'disk' => $media_item->disk,
                        'conversions_disk' => $media_item->conversions_disk,
                        'size' => $media_item->size,
                        'manipulations' => $media_item->manipulations,
                        'custom_properties' => $media_item->custom_properties,
                        'generated_conversions' => $media_item->generated_conversions,
                        'responsive_images' => $media_item->responsive_images,
                        'order_column' => $media_item->order_column,
                        'created_at' => $media_item->created_at,
                        'updated_at' => $media_item->updated_at,
                    ]);
                    
                    $progressBar->advance();
                }
                
                $progressBar->finish();
                $this->info(' -> Archivos (media) migrados con éxito.');
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE MEDIA COMPLETADA EXITOSAMENTE!");
            
        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN DE MEDIA: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de media: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
