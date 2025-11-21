<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConsolidateBranches extends Command
{
    /**
     * The name and signature of the console command.
     * N° justo despues de branchProducts. Sirve para borrar duplicados de clientes y pasar los productos registrados a la sucursal matriz. hecho
     * @var string
     */
    protected $signature = 'app:consolidate-branches';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolida sucursales con nombres duplicados y migra los productos y su historial de precios de las sucursales hijas a las matrices.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Iniciando la consolidación de sucursales y migración de datos...");

        try {
            // Usamos una única transacción para asegurar la integridad de todos los datos.
            // Si algo falla, se revierte todo el proceso.
            DB::transaction(function () {
                $this->migrateChildProductsToParent();
                $this->processDuplicateBranches();
            });

            $this->line('');
            $this->info('¡Proceso completado exitosamente!');
            $this->info('Las sucursales han sido consolidadas y los productos e historiales de precios migrados.');

        } catch (Throwable $e) {
            $this->error("\n\nERROR DURANTE LA CONSOLIDACIÓN: " . $e->getMessage());
            $this->error("No se realizó ningún cambio en la base de datos. Revisa el error y vuelve a intentarlo.");
            Log::error('Error en app:consolidate-branches: ' . $e->getFile() . ' en línea ' . $e->getLine() . ' - ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Busca y procesa las sucursales hijas que tienen el mismo nombre que su matriz.
     */
    private function processDuplicateBranches()
    {
        $this->line('');
        $this->info('2. Buscando y consolidando sucursales duplicadas...');

        // Obtenemos solo las sucursales matrices (sin padre)
        $parentBranches = Branch::whereNull('parent_branch_id')->with('children')->get();
        $progressBar = $this->output->createProgressBar($parentBranches->count());
        $deletedCount = 0;

        foreach ($parentBranches as $parent) {
            // Buscamos hijas con el mismo nombre que la matriz
            $duplicates = $parent->children->where('name', $parent->name);

            foreach ($duplicates as $duplicate) {
                $this->warn("\n - Se encontró sucursal duplicada: '{$duplicate->name}' (ID: {$duplicate->id}). Pasando sus contactos a la matriz (ID: {$parent->id}).");

                // Migrar contactos: Cambiamos el contactable_id al ID de la matriz.
                // Usamos el FQCN (Fully Qualified Class Name) para el tipo polimórfico.
                Contact::where('contactable_id', $duplicate->id)
                       ->where('contactable_type', Branch::class)
                       ->update(['contactable_id' => $parent->id]);

                $this->line("   -> Contactos de la sucursal duplicada han sido migrados.");

                // Eliminar la sucursal duplicada
                $duplicate->delete();
                $deletedCount++;
                $this->line("   -> Sucursal duplicada (ID: {$duplicate->id}) eliminada.");
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n -> Proceso de consolidación finalizado. Se eliminaron {$deletedCount} sucursales duplicadas.");
    }

    /**
     * Migra las relaciones de productos y el historial de precios de todas las sucursales hijas a sus respectivas matrices.
     */
    private function migrateChildProductsToParent()
    {
        $this->line('');
        $this->info('1. Migrando productos e historial de precios de todas las sucursales hijas a sus matrices...');

        // Obtenemos todas las sucursales que son hijas (y que aún existen)
        $childBranches = Branch::whereNotNull('parent_branch_id')->get();
        $progressBar = $this->output->createProgressBar($childBranches->count());

        foreach ($childBranches as $child) {
            $parentId = $child->parent_branch_id;

            // Obtener IDs de productos del hijo
            $childProductIds = DB::table('branch_product')->where('branch_id', $child->id)->pluck('product_id');

            if ($childProductIds->isNotEmpty()) {
                // Obtener IDs de productos que el padre YA tiene para no duplicarlos
                $parentProductIds = DB::table('branch_product')->where('branch_id', $parentId)->pluck('product_id')->flip();

                $productsToInsert = [];
                foreach ($childProductIds as $productId) {
                    // Si el producto no existe en el padre, se prepara para insertarlo
                    if (!isset($parentProductIds[$productId])) {
                        $productsToInsert[] = [
                            'branch_id' => $parentId,
                            'product_id' => $productId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Insertar en lote los nuevos productos para el padre (si hay alguno)
                if (!empty($productsToInsert)) {
                    DB::table('branch_product')->insert($productsToInsert);
                }
            }

            // --- INICIO DE LA LÓGICA AGREGADA ---
            // Migrar el historial de precios cambiando el branch_id por el de la sucursal matriz.
            // Esto asegura que todos los registros de precios históricos de la sucursal hija
            // ahora apunten a la sucursal matriz.
            DB::table('branch_price_history')
                ->where('branch_id', $child->id)
                ->update(['branch_id' => $parentId]);
            // --- FIN DE LA LÓGICA AGREGADA ---

            // Finalmente, eliminar todas las asociaciones de productos del hijo, ya que fueron migradas.
            DB::table('branch_product')->where('branch_id', $child->id)->delete();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n -> Migración de productos e historial de precios finalizada.");
    }
}
