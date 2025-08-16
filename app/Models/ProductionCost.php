<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProductionCost extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'name',
        'description',
        'cost_type',
        'cost',
        'estimated_time_seconds',
        'is_active',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'estimated_time_seconds' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relaciones

/**
 * Los productos de catálogo que utilizan este proceso de producción.
 */
public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'product_production_cost')
                ->withPivot('order')
                ->withTimestamps();
}
}
