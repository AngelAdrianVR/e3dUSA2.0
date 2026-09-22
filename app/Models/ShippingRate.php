<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ShippingRate extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'product_family_id',
        'quantity',
        'length_cm',
        'width_cm',
        'height_cm',
        'weight_kg',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'weight_kg' => 'decimal:2',
    ];

    public function productFamily(): BelongsTo
    {
        return $this->belongsTo(ProductFamily::class);
    }
}
