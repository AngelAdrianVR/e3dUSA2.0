<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\NewProductProposal;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Servicio para la categoría "Muestras y regalos".
 *
 * Estos productos son intencionalmente simples: nombre, descripción, imagen y stock
 * disponible. Se usan en las órdenes de venta de tipo muestra/regalo (no requieren
 * estar vinculados a un cliente ni generan producción).
 */
class MuestraProductService
{
    public const PRODUCT_TYPE = 'Muestra';

    /** Marca genérica usada por los productos de muestra/regalo (el campo brand_id es obligatorio). */
    public const DEFAULT_BRAND_NAME = 'SIN MARCA';

    /**
     * Crea un producto de la categoría "Muestras y regalos".
     */
    public function create(string $name, ?string $description = null, $stock = 0): Product
    {
        $product = Product::create([
            'name' => $name,
            'description' => $description,
            'code' => $this->generateCode(),
            'product_type' => self::PRODUCT_TYPE,
            'brand_id' => $this->defaultBrandId(),
            'cost' => 0,
            'base_price' => 0,
            'currency' => 'MXN',
            'is_sellable' => true,
            'is_purchasable' => false,
            'is_used_as_component' => false,
            'measure_unit' => 'Pieza(s)',
            'min_quantity' => 1,
        ]);

        $product->storages()->create([
            'quantity' => $stock,
            'location' => null,
        ]);

        return $product;
    }

    /**
     * Registra automáticamente una propuesta de producto del seguimiento de muestras
     * como producto de la categoría "Muestras y regalos" y la vincula (product_id).
     *
     * @param  float|int  $stock  Stock disponible capturado por el usuario al registrar.
     */
    public function createFromProposal(NewProductProposal $proposal, $stock = 0): Product
    {
        $product = $this->create(
            $proposal->name,
            $proposal->description,
            $stock
        );

        // Copiamos la imagen capturada en el seguimiento de muestras.
        if ($proposal->hasMedia('images')) {
            $proposal->getFirstMedia('images')->copy($product, 'images');
        }

        $proposal->update([
            'product_id' => $product->id,
            'status' => 'Aprobado',
            'approved_at' => now(),
        ]);

        return $product;
    }

    /**
     * Genera un código único para el producto de muestra.
     */
    public function generateCode(): string
    {
        do {
            $code = 'MUE-' . strtoupper(Str::random(6));
        } while (Product::where('code', $code)->exists());

        return $code;
    }

    private function defaultBrandId(): int
    {
        return Brand::firstOrCreate(['name' => self::DEFAULT_BRAND_NAME])->id;
    }
}
