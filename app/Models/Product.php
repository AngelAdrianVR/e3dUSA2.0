<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    // relaciones
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function companyBranches(): BelongsToMany
    {
        return $this->belongsToMany(CompanyBranch::class, 'branch_pricing')
                    ->withPivot('price')
                    ->withTimestamps();
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'catalog_product_id', 'component_product_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    /**
     * Define la relación para obtener los productos en los que este producto es utilizado.
     * (Este producto es el COMPONENTE).
     */
    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_components', 'component_product_id', 'catalog_product_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    /**
     * Los procesos de producción asociados a este producto de catálogo.
     */
    public function productionCosts(): BelongsToMany
    {
    return $this->belongsToMany(ProductionCost::class, 'product_production_cost')
                ->withPivot('order') // ¡Importante! Especifica los campos adicionales
                ->withTimestamps()
                ->orderBy('pivot_order', 'asc'); // Ordena los procesos según el campo 'order'
    }
}
