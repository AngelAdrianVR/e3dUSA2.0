<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Shipment extends Model implements HasMedia, Auditable
{
    use InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'id',
        'sale_id',
        'status', // Pendiente, Enviado
        'promise_date',
        'shipping_company',
        'tracking_guide',
        'number_of_packages',
        'shipping_cost',
        'notes',
        'sent_by',
        'sent_at',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // --- RELACIONES ---

    /** Un envío pertenece a una venta */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Un envío contiene muchos productos */
    public function shipmentProducts(): HasMany
    {
        return $this->hasMany(ShipmentProduct::class);
    }
}
