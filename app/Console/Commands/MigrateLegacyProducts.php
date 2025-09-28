<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MigrateLegacyProducts extends Command
{
    /**
     * The name and signature of the console command. hecho.
     * N°3
     * @var string
     */
    protected $signature = 'app:migrate-legacy-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migra Raw Materials y Catalog Products de la BD antigua a la nueva tabla unificada de Products.';

    private $brandsMap = [];
    private $familiesMap = [];
    private $materialsMap = [];
    private $codeConsecutive = 1;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando la migración de Productos...");

        if ($this->confirm('¿Deseas eliminar TODOS los datos de las tablas "products" y "storages" antes de empezar? Se recomienda para una migración limpia.', true)) {
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                DB::table('storages')->truncate();
                DB::table('products')->truncate();
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->warn('Las tablas "products" y "storages" han sido limpiadas.');
            } catch (Throwable $e) {
                $this->error("Error al limpiar las tablas: " . $e->getMessage());
                return 1;
            }
        }

        try {
            $this->info('Preparando mapeos de datos desde archivos...');
            $this->setupMappings();
            $this->info('Mapeos completados.');

            $oldDb = DB::connection('mysql_old');
            $newDb = DB::connection('mysql');

            // Este mapa de IDs debe ser idéntico al del comando MigrateLegacyMedia para que coincidan.
            $productIdMap = $this->generateProductIdMap($oldDb);

            $newDb->transaction(function () use ($oldDb, $newDb, $productIdMap) {
                $this->migrateRawMaterials($oldDb, $newDb, $productIdMap['raw_materials']);
                $this->migrateCatalogProducts($oldDb, $newDb, $productIdMap['catalog_products']);
            });

            $this->line('');
            $this->info("\n¡MIGRACIÓN DE PRODUCTOS COMPLETADA EXITOSAMENTE!");

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA MIGRACIÓN DE PRODUCTOS: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la nueva base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en migración de productos: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }
        return 0;
    }

    /**
     * Carga los datos de los archivos .txt en arrays para un mapeo rápido.
     */
    private function setupMappings()
    {
        // Mapeo de Brands
        $brandsContent = file_get_contents(base_path('brands.txt'));
        preg_match_all("/\((\d+), '([^']*)'/", $brandsContent, $matches);
        foreach ($matches[1] as $index => $id) {
            $this->brandsMap[strtoupper(trim($matches[2][$index]))] = $id;
        }

        // Mapeo de Familias
        $familiesContent = file_get_contents(base_path('familias.txt'));
        preg_match_all("/\(\d+, '[^']*', '([^']*)'/", $familiesContent, $matches);
        foreach ($matches[1] as $index => $code) {
             // Asumo que el ID de la familia es el índice + 1 basado en el archivo.
             // Si el ID es el primer número, ajusta la regex.
            $this->familiesMap[strtoupper(trim($code))] = $index + 1;
        }
        
        // Mapeo de Materiales (Value => Key)
        $materialsContent = file_get_contents(base_path('Materials.txt'));
        preg_match_all("/'([^']*)'\s*=>\s*'([^']*)'/", $materialsContent, $matches);
        foreach ($matches[2] as $index => $name) {
            $this->materialsMap[strtoupper(trim($name))] = strtoupper(trim($matches[1][$index]));
        }
    }

    /**
     * Genera el mapa de IDs de productos en el orden correcto para coincidir con la migración de media.
     */
    private function generateProductIdMap($oldDb): array
    {
        $map = ['raw_materials' => [], 'catalog_products' => []];
        $nextProductId = 1;

        $oldDb->table('raw_materials')->orderBy('id')->pluck('id')->each(function ($id) use (&$map, &$nextProductId) {
            $map['raw_materials'][$id] = $nextProductId++;
        });

        $oldDb->table('catalog_products')->orderBy('id')->pluck('id')->each(function ($id) use (&$map, &$nextProductId) {
            $map['catalog_products'][$id] = $nextProductId++;
        });
        
        return $map;
    }

    /**
     * Migra todos los registros de la tabla raw_materials.
     */
    private function migrateRawMaterials($oldDb, $newDb, $idMap)
    {
        $this->line('');
        $this->info('Migrando Materias Primas e Insumos...');
        $raw_materials = $oldDb->table('raw_materials')->orderBy('id')->get();
        $progressBar = $this->output->createProgressBar($raw_materials->count());

        foreach ($raw_materials as $raw_material) {
            $isInsumo = str_starts_with(strtolower($raw_material->part_number), 'ins');
            $productType = $isInsumo ? 'Insumo' : 'Materia Prima';

            $newProductData = [
                'id' => $idMap[$raw_material->id],
                'name' => $raw_material->name,
                'product_type' => $productType,
                'is_used_as_component' => true,
                'code' => $this->generateProductCode($isInsumo ? 'I' : 'MP', $raw_material),
                'base_price' => null,
                'base_price_updated_at' => null,
                'is_sellable' => false,
                'is_purchasable' => true,
                'archived_at' => null,
                'material' => $raw_material->material,
                'large' => $raw_material->large,
                'height' => $raw_material->height,
                'width' => $raw_material->width,
                'diameter' => $raw_material->diameter,
                'measure_unit' => $raw_material->measure_unit,
                'currency' => 'MXN', // Asumiendo MXN por defecto
                'caracteristics' => $raw_material->description,
                'cost' => $raw_material->cost,
                'min_quantity' => $raw_material->min_quantity,
                'max_quantity' => $raw_material->max_quantity,
                'brand_id' => $this->brandsMap[strtoupper(trim($raw_material->brand))] ?? 176, // 176 = GENERICO por defecto
                'product_family_id' => $this->getFamilyId($raw_material->part_number),
                'created_at' => $raw_material->created_at,
                'updated_at' => $raw_material->updated_at,
            ];

            $newDb->table('products')->insert($newProductData);
            
            $newDb->table('storages')->insert([
                'storable_id' => $newProductData['id'],
                'storable_type' => 'App\Models\Product',
                'quantity' => 0,
                'location' => null,
                'created_at' => $raw_material->created_at,
                'updated_at' => $raw_material->updated_at,
            ]);

            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * Migra todos los registros de la tabla catalog_products.
     */
    private function migrateCatalogProducts($oldDb, $newDb, $idMap)
    {
        $this->line('');
        $this->info('Migrando Productos de Catálogo...');
        $catalog_products = $oldDb->table('catalog_products')->orderBy('id')->get();
        $progressBar = $this->output->createProgressBar($catalog_products->count());

        foreach ($catalog_products as $catalog_product) {
            $newProductData = [
                'id' => $idMap[$catalog_product->id],
                'name' => $catalog_product->name,
                'product_type' => 'Catálogo',
                'is_used_as_component' => false,
                'code' => $this->generateProductCode(null, $catalog_product),
                'base_price' => 0,
                'base_price_updated_at' => null,
                'is_sellable' => true,
                'is_purchasable' => false, // Asumiendo que los de catálogo no se compran
                'archived_at' => null,
                'material' => json_decode($catalog_product->features)->material ?? null,
                'large' => $catalog_product->large,
                'height' => $catalog_product->height,
                'width' => $catalog_product->width,
                'diameter' => $catalog_product->diameter,
                'measure_unit' => $catalog_product->measure_unit,
                'currency' => 'MXN', // Asumiendo MXN por defecto
                'caracteristics' => $catalog_product->description,
                'cost' => $catalog_product->cost,
                'min_quantity' => $catalog_product->min_quantity ?? 1,
                'max_quantity' => $catalog_product->max_quantity,
                'brand_id' => $this->brandsMap[strtoupper(trim($catalog_product->brand))] ?? 176, // 176 = GENERICO por defecto
                'product_family_id' => $this->getFamilyId($catalog_product->part_number),
                'created_at' => $catalog_product->created_at,
                'updated_at' => $catalog_product->updated_at,
            ];

            $newDb->table('products')->insert($newProductData);

            $newDb->table('storages')->insert([
                'storable_id' => $newProductData['id'],
                'storable_type' => 'App\Models\Product',
                'quantity' => 0,
                'location' => null,
                'created_at' => $catalog_product->created_at,
                'updated_at' => $catalog_product->updated_at,
            ]);

            $progressBar->advance();
        }
        $progressBar->finish();
    }

    /**
     * Genera el código de producto único basado en las reglas.
     */
    private function generateProductCode($type, $oldProduct): string
    {
        $parts = [];
        $materialKey = $this->getMaterialKey($oldProduct->material ?? null, $oldProduct->features);
        $brandAbbr = substr(strtoupper(trim($oldProduct->brand)), 0, 3);
        $consecutive = str_pad($this->codeConsecutive++, 4, '0', STR_PAD_LEFT);

        if ($type) {
            $parts[] = $type;
        }
        if ($materialKey) {
            $parts[] = $materialKey;
        }
        $parts[] = $brandAbbr;
        $parts[] = $consecutive;
        
        return implode('-', $parts);
    }

    /**
     * Extrae el ID de la familia del part_number.
     */
    private function getFamilyId($partNumber): ?int
    {
        $parts = explode('-', $partNumber);
        if (isset($parts[1])) {
            $familyCode = strtoupper(trim($parts[1]));
            return $this->familiesMap[$familyCode] ?? null;
        }
        return null;
    }

    /**
     * Obtiene la clave del material desde el campo 'features' o el campo de material.
     */
    private function getMaterialKey($material, $featuresJson): string
    {
        $features = json_decode($featuresJson, true);
        $materialName = $features['material'] ?? $material;
        
        if ($materialName) {
            return $this->materialsMap[strtoupper(trim($materialName))] ?? '';
        }

        return '';
    }
}

