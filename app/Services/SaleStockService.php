<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\StockMovement;
use App\Models\StockShortage;
use App\Models\Storage;

/**
 * Concentra los movimientos de inventario de las órdenes de venta / stock.
 *
 * Además de descontar existencias, guarda en `stock_shortages` la cantidad que no
 * pudo cubrirse con stock (adeudo). Esa cantidad se ofrece rebajar cuando el
 * producto entra nuevamente al almacén.
 */
class SaleStockService
{
    /**
     * Aplica los descuentos de inventario de una orden recién creada o clonada.
     */
    public function apply(Sale $sale): void
    {
        $sale->load([
            'saleProducts.product.storages',
            'saleProducts.product.components.storages',
            'saleProducts.product.parent.components.storages',
        ]);

        $isSale = $sale->type === 'venta';

        foreach ($sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;

            if (!$product) {
                continue;
            }

            $quantity = (float) $saleProduct->quantity;

            if ($isSale) {
                $this->applyFinishedProduct($sale, $saleProduct, $product, $quantity);
            } else {
                // En órdenes de stock el producto terminado es lo que se va a producir.
                $saleProduct->update(['quantity_to_produce' => $quantity]);
            }

            $this->applyComponents($sale, $saleProduct, $product, $quantity, $isSale);
        }
    }

    /**
     * Revierte los descuentos de inventario de una orden antes de recalcularlos o eliminarla.
     *
     * La cantidad a devolver se calcula con las piezas que siguen pendientes: lo que se
     * descontó originalmente es (cantidad requerida - cantidad pendiente).
     */
    public function revert(Sale $sale): void
    {
        $sale->load([
            'saleProducts.product.storages',
            'saleProducts.product.components.storages',
            'saleProducts.product.parent.components.storages',
            'saleProducts.shortages',
        ]);

        $isSale = $sale->type === 'venta';

        foreach ($sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;

            if (!$product) {
                continue;
            }

            $quantity = (float) $saleProduct->quantity;
            $shortages = $saleProduct->shortages;

            if ($isSale) {
                // Adeudos de producto terminado de esta línea (los productos compuestos no los generan).
                $finishedShortages = $shortages
                    ->where('product_id', $product->id)
                    ->where('is_component', false);

                // Si existen adeudos registrados se usa lo que sigue pendiente; si no existen
                // (productos compuestos, órdenes antiguas o líneas cubiertas al 100% por stock)
                // se conserva el cálculo anterior basado en quantity_to_produce.
                $takenFromStock = $finishedShortages->isNotEmpty()
                    ? round(max($quantity - (float) $finishedShortages->sum('quantity_pending'), 0), 2)
                    : round(max($quantity - (float) $saleProduct->quantity_to_produce, 0), 2);

                $storage = $product->storages->first();

                if ($takenFromStock > 0 && $storage) {
                    $storage->increment('quantity', $takenFromStock);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'storage_id' => $storage->id,
                        'quantity_change' => $takenFromStock,
                        'type' => 'Entrada',
                        'notes' => "Reversión por Orden de venta #{$sale->id}",
                    ]);
                }
            }

            foreach ($product->actual_components as $component) {
                $requiredQuantity = (float) $component->pivot->quantity * $quantity;

                $componentShortages = $shortages
                    ->where('product_id', $component->id)
                    ->where('is_component', true);

                $deducted = $componentShortages->isNotEmpty()
                    ? round(max($requiredQuantity - (float) $componentShortages->sum('quantity_pending'), 0), 2)
                    : $requiredQuantity;

                $componentStorage = $component->storages->first();

                if ($deducted > 0 && $componentStorage) {
                    $componentStorage->increment('quantity', $deducted);

                    StockMovement::create([
                        'product_id' => $component->id,
                        'storage_id' => $componentStorage->id,
                        'quantity_change' => $deducted,
                        'type' => 'Entrada',
                        'notes' => "Reversión de material para Orden #{$sale->id}",
                    ]);
                }
            }
        }

        StockShortage::where('sale_id', $sale->id)->delete();
    }

    private function applyFinishedProduct(Sale $sale, SaleProduct $saleProduct, Product $product, float $quantity): void
    {
        $storage = $product->storages->first();
        $available = $storage ? max((float) $storage->quantity, 0) : 0;

        $takenFromStock = min($quantity, $available);
        $shortage = round($quantity - $takenFromStock, 2);

        $saleProduct->update(['quantity_to_produce' => $shortage]);

        if ($takenFromStock > 0 && $storage) {
            $storage->decrement('quantity', $takenFromStock);

            StockMovement::create([
                'product_id' => $product->id,
                'storage_id' => $storage->id,
                'quantity_change' => $takenFromStock,
                'type' => 'Salida',
                'notes' => "Descuento por Orden de venta #{$sale->id}",
            ]);
        }

        if ($shortage > 0 && $product->actual_components->isEmpty()) {
            // Los productos compuestos no generan adeudo de producto terminado:
            // su faltante se controla por los componentes que se rebajan.
            $this->recordShortage($sale, $saleProduct, $product, $storage, $shortage, false);
        }
    }

    private function applyComponents(Sale $sale, SaleProduct $saleProduct, Product $product, float $quantity, bool $isSale): void
    {
        foreach ($product->actual_components as $component) {
            $requiredQuantity = (float) $component->pivot->quantity * $quantity;

            if ($requiredQuantity <= 0) {
                continue;
            }

            $componentStorage = $component->storages->first();
            $available = $componentStorage ? max((float) $componentStorage->quantity, 0) : 0;

            $discountQuantity = min($requiredQuantity, $available);
            $shortage = round($requiredQuantity - $discountQuantity, 2);

            if ($discountQuantity > 0 && $componentStorage) {
                $componentStorage->decrement('quantity', $discountQuantity);

                $notes = $isSale
                    ? "Descuento de componentes para {$quantity} de {$product->name} (Orden #{$sale->id})"
                    : "Descuento de componentes para {$quantity} de {$product->name} para stock.";

                StockMovement::create([
                    'product_id' => $component->id,
                    'storage_id' => $componentStorage->id,
                    'quantity_change' => $discountQuantity,
                    'type' => 'Salida',
                    'notes' => $notes,
                ]);
            }

            if ($isSale && $shortage > 0) {
                $this->recordShortage($sale, $saleProduct, $component, $componentStorage, $shortage, true);
            }
        }
    }

    private function recordShortage(
        Sale $sale,
        SaleProduct $saleProduct,
        Product $product,
        ?Storage $storage,
        float $quantity,
        bool $isComponent
    ): void {
        StockShortage::create([
            'sale_id' => $sale->id,
            'sale_product_id' => $saleProduct->id,
            'product_id' => $product->id,
            'storage_id' => $storage?->id,
            'quantity' => $quantity,
            'quantity_pending' => $quantity,
            'is_component' => $isComponent,
            'notes' => $isComponent
                ? "Faltante de {$product->name} (componente) para {$saleProduct->quantity} de {$saleProduct->product?->name}"
                : "Faltante de {$product->name} para la orden",
        ]);
    }
}
