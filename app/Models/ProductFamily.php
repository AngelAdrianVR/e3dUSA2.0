<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProductFamily extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'name',
        'key',
        'sat_code',
    ];

    /**
     * Tarifas de envío (fichas de especificaciones de caja) de la familia.
     */
    public function shippingRates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

}
