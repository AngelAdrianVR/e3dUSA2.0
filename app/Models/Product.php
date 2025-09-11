<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Product extends Model implements HasMedia, Auditable
{
    use InteractsWithMedia, AuditableTrait;
    
    protected $fillable = [
        'cost',
        'name',
        'code',
        'large',
        'width',
        'height',
        'material',
        'diameter',
        'brand_id',
        'currency', // Moneda MXN, USD
        'base_price',
        'is_sellable',
        'archived_at',
        'product_type',
        'measure_unit',
        'min_quantity',
        'max_quantity',
        'caracteristics',
        'is_purchasable',
        'product_family_id',
        'base_price_updated_at', // fecha de la ultima actualización de precio base (para revision automatica de cada año)
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'is_sellable' => 'boolean',
        'is_purchasable' => 'boolean',
    ];

    // protected $appends = ['images_urls']; // se incluirá en el JSON un arreglo con las URLs de las imágenes


    // relaciones
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }

    public function storages(): MorphMany
    {
        return $this->morphMany(Storage::class, 'storable');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(branch::class);
    }

    /**
     * Un producto de catálogo puede estar en muchos seguimientos de muestras.
     */
    public function sampleItems(): MorphMany
    {
        return $this->morphMany(SampleTrackingItem::class, 'itemable');
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'catalog_product_id', 'component_product_id')
                    ->withPivot('quantity', 'cost')
                    ->withTimestamps();
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier')
            ->withPivot('last_price', 'supplier_sku', 'min_quantity')
            ->withTimestamps();
    }

    /**
     * Define la relación para obtener los productos en los que este producto es utilizado.
     * (Este producto es el COMPONENTE).
     */
    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'component_product_id', 'catalog_product_id')
                    ->withPivot('quantity', 'cost')
                    ->withTimestamps();
    }

    /**
     * Los procesos de producción asociados a este producto de catálogo.
     */
    public function productionCosts(): BelongsToMany
    {
    return $this->belongsToMany(ProductionCost::class, 'product_production_cost')
                ->withPivot('order', 'cost')
                ->withTimestamps()
                ->orderBy('pivot_order', 'asc'); // Ordena los procesos según el campo 'order'
    }

    /**
     * Obtiene el historial de precios de este producto en todas las sucursales.
     */
    public function priceHistory()
    {
        return $this->hasMany(BranchPriceHistory::class);
    }

    /**
     * Obtiene las sucursales para las que este producto es sugerido.
     */
    public function suggestedForBranches()
    {
        return $this->belongsToMany(Branch::class, 'branch_suggested_products');
    }

    // ==== ACCESORES ==== // comentado porque carga la media aunque no quiera.
    // public function getImagesUrlsAttribute()
    // {
    //     return $this->getMedia('images')->map(function ($media) {
    //         return $media->getUrl(); // también existe getFullUrl() si usas rutas absolutas
    //     });
    // }
}
