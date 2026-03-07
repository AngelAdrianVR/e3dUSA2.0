<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class QuoteProduct extends Pivot implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quote_products';

    // Para que los IDs y timestamps funcionen correctamente al usarlo como modelo normal
    public $incrementing = true;
    
    protected $fillable = [
        'id',
        'quote_id',
        'product_id',
        'custom_name',
        'custom_cost',
        'custom_measure_unit',
        'quantity',
        'unit_price',
        'notes',
        'customization_details',
        'show_image', // muestra o no la imagen del producto en la cotización.
        'customer_approval_status', // 'Pendiente', 'Aprobado', 'Rechazado'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'decimal:2',
        'custom_cost' => 'decimal:2',
        'customization_details' => 'array', // para modificaciones especiales
        'show_image' => 'boolean',
    ];

    // --- CONFIGURACIÓN DE MEDIA LIBRARY ---
    
    public function registerMediaCollections(): void
    {
        // Colección específica para las imágenes de los productos customizados
        $this->addMediaCollection('custom_product_images')
             ->singleFile(); // Si solo quieres permitir 1 imagen por producto cotizado. Quita esto si quieres una galería.
    }

    // --- RELACIONES ---

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // --- ACCESORS PARA UNIFICAR DATOS (Catálogo vs Custom) ---

    /**
     * Obtiene el nombre del producto, ya sea del catálogo o el escrito manualmente.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->product_id ? $this->product->name : ($this->custom_name ?? 'Producto sin nombre');
    }

    /**
     * Obtiene el costo unitario real para cálculo de utilidades.
     */
    public function getUnitCostAttribute(): float
    {
        return $this->product_id ? (float) $this->product->cost : (float) $this->custom_cost;
    }

    /**
     * Verifica si es un producto temporal/al vuelo.
     */
    public function getIsCustomAttribute(): bool
    {
        return is_null($this->product_id);
    }

    /**
     * Obtiene la URL de la imagen (Del catálogo o la subida temporalmente)
     */
    public function getImageUrlAttribute(): ?string
    {
        // Si es producto de catálogo, asumo que tienes lógica de imágenes en tu modelo Product
        if ($this->product_id && $this->product) {
            // Ajusta esto según cómo guardes las imágenes en tu modelo Product original
            return $this->product->getFirstMediaUrl('images') ?: null; 
        }

        // Si es un producto custom, devolvemos la imagen adjunta a este QuoteProduct
        return $this->getFirstMediaUrl('custom_product_images') ?: null;
    }

    // --- LÓGICA DE NEGOCIO ---

    /**
     * Convierte este producto temporal en un producto real en el catálogo.
     * @param array $extraData Datos adicionales requeridos (ej. brand_id, product_type, code)
     * @return Product
     */
    public function convertToCatalogProduct(array $extraData): Product
    {
        if (!$this->is_custom) {
            throw new \Exception("Esta partida ya pertenece a un producto del catálogo.");
        }

        // Crear el producto en el catálogo
        $product = Product::create(array_merge([
            'name' => $this->custom_name,
            'cost' => $this->custom_cost,
            'base_price' => $this->unit_price,
            'measure_unit' => $this->custom_measure_unit,
            // Valores por defecto seguros:
            'is_sellable' => true,
            'is_purchasable' => true,
            'is_used_as_component' => false,
        ], $extraData));

        // Mover la imagen temporal al nuevo producto del catálogo
        if ($this->hasMedia('custom_product_images')) {
            $mediaItem = $this->getFirstMedia('custom_product_images');
            // Mueve la imagen a la colección de imágenes del producto (ajusta 'images' según tu lógica)
            $mediaItem->move($product, 'images'); 
        }

        // Enlazar esta partida de la cotización al nuevo producto
        $this->update([
            'product_id' => $product->id,
            'custom_name' => null,
            'custom_cost' => null,
            'custom_measure_unit' => null,
        ]);

        return $product;
    }
}