<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Actualización o evento registrado sobre una guía de paquetería.
 *
 * A diferencia de la fecha tentativa de arribo (que vive en la guía), cada
 * evento tiene su propia fecha y se muestra en el calendario de arribos.
 */
class RequisitionShipmentEvent extends Model
{
    use HasFactory;

    /**
     * Ubicaciones / etapas sugeridas al registrar una actualización.
     */
    public const LOCATIONS = ['China', 'GDL', 'Aduanas'];

    protected $fillable = [
        'requisition_shipment_id',
        'event_date',
        'location',
        'description',
        'created_by',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    /**
     * Texto visible del evento: ubicación y descripción concatenadas.
     */
    public function getLabelAttribute(): string
    {
        return trim(implode(' · ', array_filter([$this->location, $this->description])));
    }

    // --- RELACIONES ---

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(RequisitionShipment::class, 'requisition_shipment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
