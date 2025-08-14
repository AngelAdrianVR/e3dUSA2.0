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
}
