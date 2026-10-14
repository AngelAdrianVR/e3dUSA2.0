<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RequisitionItem extends Model
{
    use HasFactory;

    public const ARRIVAL_COLORS = Requisition::ARRIVAL_COLORS;

    protected $fillable = [
        'requisition_id',
        'product_id',
        'quantity',
        'unit_cost',
        'description',
        'arrival_status',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    protected $appends = ['arrival_color'];

    public function getArrivalColorAttribute(): string
    {
        return self::ARRIVAL_COLORS[$this->arrival_status] ?? self::ARRIVAL_COLORS['Pendiente'];
    }

    // --- RELACIONES ---

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Guías de paquetería en las que viaja este producto.
     */
    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(RequisitionShipment::class, 'requisition_item_shipment')
            ->withTimestamps();
    }
}
