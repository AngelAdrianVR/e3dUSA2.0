<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequisitionShipment extends Model
{
    use HasFactory;

    public const STATUSES = ['Pendiente', 'En tránsito', 'Incidencia', 'Arribado'];

    /**
     * Color asociado al estatus de la guía en el calendario de arribos.
     */
    public const STATUS_COLORS = [
        'Pendiente' => '#9CA3AF',   // Gris: aún no llega a destino
        'En tránsito' => '#F59E0B', // Naranja: en proceso / en tránsito
        'Incidencia' => '#EF4444',  // Rojo: problemas o alertas críticas
        'Arribado' => '#22C55E',    // Verde: arribo confirmado
    ];

    protected $fillable = [
        'tracking_number',
        'carrier',
        'status',
        'estimated_arrival_date',
        'arrived_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'estimated_arrival_date' => 'date',
        'arrived_at' => 'datetime',
    ];

    public function getColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? self::STATUS_COLORS['Pendiente'];
    }

    // --- RELACIONES ---

    /**
     * Partidas (productos) que viajan en esta guía.
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(RequisitionItem::class, 'requisition_item_shipment')
            ->withTimestamps();
    }

    /**
     * Requisiciones involucradas en la guía (una guía puede agrupar varias).
     */
    public function requisitions(): BelongsToMany
    {
        return $this->belongsToMany(Requisition::class, 'requisition_item_shipment', 'requisition_shipment_id', 'requisition_id')
            ->withTimestamps()
            ->distinct();
    }

    /**
     * Actualizaciones / eventos de seguimiento de la guía.
     */
    public function events(): HasMany
    {
        return $this->hasMany(RequisitionShipmentEvent::class)->orderBy('event_date');
    }

    /**
     * Sincroniza las partidas ligadas a la guía conservando la requisición
     * de cada partida en la tabla pivote.
     *
     * @param  array<int, int|string>  $itemIds
     */
    public function syncItems(array $itemIds): void
    {
        $items = RequisitionItem::whereIn('id', $itemIds)->get();

        $this->items()->sync(
            $items->mapWithKeys(fn (RequisitionItem $item) => [
                $item->id => ['requisition_id' => $item->requisition_id],
            ])->all()
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
