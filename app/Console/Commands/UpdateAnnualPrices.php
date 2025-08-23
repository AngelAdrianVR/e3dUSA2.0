<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BranchPriceHistory;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateAnnualPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:update-annual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza anualmente los precios especiales de clientes y los precios base de productos de catálogo.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando la actualización de precios anual...');
        Log::info('Iniciando la tarea programada de actualización de precios anual.');

        // Usamos una transacción para asegurar que todas las operaciones se completen con éxito.
        DB::transaction(function () {
            $this->updateSpecialPrices();
            $this->updateCatalogBasePrices();
        });

        $this->info('¡Actualización de precios anual completada con éxito!');
        Log::info('Tarea programada de actualización de precios anual finalizada.');
    }

    /**
     * Actualiza los precios especiales de clientes con más de un año de antigüedad.
     */
    private function updateSpecialPrices()
    {
        $oneYearAgo = Carbon::now()->subYear();
        
        // 1. Obtener todos los precios especiales que están activos y tienen más de un año.
        $pricesToUpdate = BranchPriceHistory::whereNull('valid_to')
            ->where('valid_from', '<=', $oneYearAgo)
            ->get();

        if ($pricesToUpdate->isEmpty()) {
            $this->line('No hay precios especiales de clientes para actualizar.');
            return;
        }

        $this->info("Se encontraron {$pricesToUpdate->count()} precios especiales para actualizar.");

        foreach ($pricesToUpdate as $oldPrice) {
            // 2. "Cerrar" el precio antiguo estableciendo la fecha de fin.
            $oldPrice->valid_to = Carbon::now()->subDay();
            $oldPrice->save();

            // 3. Crear el nuevo registro con un aumento del 7.9%
            $newPriceAmount = $oldPrice->price * 1.079;

            BranchPriceHistory::create([
                'branch_id' => $oldPrice->branch_id,
                'product_id' => $oldPrice->product_id,
                'price' => round($newPriceAmount, 2), // Redondear a 2 decimales
                'currency' => $oldPrice->currency,
                'valid_from' => Carbon::now(),
                'valid_to' => null,
            ]);

            $logMessage = "Precio especial actualizado para producto ID {$oldPrice->product_id} y cliente ID {$oldPrice->branch_id}. Precio anterior: {$oldPrice->price}, Nuevo precio: " . round($newPriceAmount, 2);
            $this->line($logMessage);
            Log::info($logMessage);
        }
    }

    /**
     * Actualiza los precios base de productos de catálogo con más de un año de antigüedad.
     */
    private function updateCatalogBasePrices()
    {
        $oneYearAgo = Carbon::now()->subYear();

        // 1. Obtener productos de catálogo cuya fecha de actualización de precio base sea de hace un año o más.
        // También incluye productos que nunca han sido actualizados (null).
        $productsToUpdate = Product::where('product_type', 'Catálogo')
            ->where(function ($query) use ($oneYearAgo) {
                $query->where('base_price_updated_at', '<=', $oneYearAgo)
                      ->orWhereNull('base_price_updated_at');
            })
            ->get();

        if ($productsToUpdate->isEmpty()) {
            $this->line('No hay precios base de catálogo para actualizar.');
            return;
        }
        
        $this->info("Se encontraron {$productsToUpdate->count()} productos de catálogo para actualizar.");

        foreach ($productsToUpdate as $product) {
            $oldPrice = $product->base_price;
            $newPrice = $oldPrice * 1.079;

            $product->base_price = round($newPrice, 2);
            $product->base_price_updated_at = Carbon::now();
            $product->save();

            $logMessage = "Precio base actualizado para producto de catálogo ID {$product->id}. Precio anterior: {$oldPrice}, Nuevo precio: " . round($newPrice, 2);
            $this->line($logMessage);
            Log::info($logMessage);
        }
    }
}
