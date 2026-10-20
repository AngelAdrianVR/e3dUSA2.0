<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class RequisitionShipment extends Model
{
    use HasFactory;

    public const STATUSES = ['Pendiente', 'En tránsito', 'Incidencia', 'Arribado'];

    /**
     * Formas de entrega de la mercancía.
     */
    public const DELIVERY_METHODS = ['Guía', 'Proveedor', 'Transporte propio'];

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
        'delivery_method',
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

    /**
     * Nombre visible del registro: número de guía o la forma de entrega.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->tracking_number) {
            return $this->tracking_number;
        }

        return ($this->delivery_method ?: 'Guía') . ' (sin guía)';
    }

    // --- RELACIONES ---

    /**
     * Partidas (productos) que viajan en esta guía, con la cantidad enviada en
     * cada una (parcialidades de entrega).
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(RequisitionItem::class, 'requisition_item_shipment')
            ->withPivot('quantity')
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
     * @param  array<int, int|string|array{id: int|string, quantity?: float|int|string|null}>  $items
     */
    public function syncItems(array $items): void
    {
        $entries = collect($items)->map(function ($entry) {
            return is_array($entry)
                ? $entry
                : ['id' => $entry, 'quantity' => null];
        })->keyBy(fn (array $entry) => (int) $entry['id']);

        $models = RequisitionItem::whereIn('id', $entries->keys()->all())->get();

        $this->items()->sync(
            $models->mapWithKeys(function (RequisitionItem $item) use ($entries) {
                $quantity = $entries[$item->id]['quantity'] ?? null;

                return [
                    $item->id => [
                        'requisition_id' => $item->requisition_id,
                        // Sin cantidad explícita se envía lo que resta por asignar.
                        'quantity' => $quantity !== null && $quantity !== ''
                            ? (float) $quantity
                            : $this->remainingQuantityFor($item),
                    ],
                ];
            })->all()
        );
    }

    /**
     * Cantidad de la partida que aún no viaja en otras guías.
     */
    public function remainingQuantityFor(RequisitionItem $item): float
    {
        $assigned = DB::table('requisition_item_shipment')
            ->where('requisition_item_id', $item->id)
            ->where('requisition_shipment_id', '!=', $this->id)
            ->sum('quantity');

        return max(0, round((float) $item->quantity - (float) $assigned, 2));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
