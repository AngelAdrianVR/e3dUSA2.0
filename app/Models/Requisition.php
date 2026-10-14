<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Requisition extends Model implements HasMedia, Auditable
{
    use HasFactory;
    use InteractsWithMedia, AuditableTrait;
    use SoftDeletes;

    /**
     * Tipos de requisición (vía de compra).
     */
    public const TYPE_LOCAL = 'Local';
    public const TYPE_IMPORT = 'Importación';

    /**
     * Vías de entrada de la requisición.
     */
    public const ORIGIN_SELLER = 'Vendedor';
    public const ORIGIN_WAREHOUSE = 'Almacén';

    /**
     * Estatus del flujo Local (compra directa con proveedor nacional).
     */
    public const LOCAL_STATUSES = [
        'Pendiente de cotización',
        'Pendiente de autorización',
        'Rechazada',
        'Autorizada',
        'Pendiente de pago',
        'Pagada',
        'Completada',
    ];

    /**
     * Estatus del flujo de Importación (fábrica / China).
     */
    public const IMPORT_STATUSES = [
        'Pendiente de negociación',
        'Rechazada',
        'Aprobada',
        'Pedido a fábrica',
        'En tránsito',
        'Arribo confirmado',
        'Completada',
    ];

    public const PRIORITIES = ['Baja', 'Media', 'Alta', 'Urgente'];

    /**
     * Frecuencia de venta / consumo de la mercancía solicitada.
     */
    public const SALES_FREQUENCIES = ['Mensual', 'Bimestral', 'Trimestral', 'Semestral', 'Anual'];

    /**
     * Estatus de arribo: define el color en el calendario de arribos.
     */
    public const ARRIVAL_STATUSES = ['Pendiente', 'En tránsito', 'Incidencia', 'Arribado'];

    public const ARRIVAL_COLORS = [
        'Pendiente' => '#9CA3AF',   // Gris: aún no llega a destino
        'En tránsito' => '#F59E0B', // Naranja: en proceso / en tránsito
        'Incidencia' => '#EF4444',  // Rojo: problemas o alertas críticas
        'Arribado' => '#22C55E',    // Verde: arribo confirmado
    ];

    protected $fillable = [
        'type',
        'origin',
        'status',
        'priority',
        'arrival_status',
        'user_id',
        'branch_id',
        'business_name',
        'product_id',
        'quantity',
        'reason',
        'sale_specification',
        'sale_frequency',
        'notes',
        'quoted_cost',
        'quoted_currency',
        'invoice_amount',
        'invoice_currency',
        'paid_at',
        'authorized_by_user_id',
        'authorized_at',
        'rejection_reason',
        'rejected_by_user_id',
        'rejected_at',
        'expected_delivery_date',
        'delivered_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quoted_cost' => 'decimal:2',
        'invoice_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'authorized_at' => 'datetime',
        'rejected_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'delivered_at' => 'datetime',
    ];

    /**
     * Atributos calculados que siempre viajan al frontend.
     */
    protected $appends = ['folio', 'arrival_color'];

    /**
     * Folio visible de la requisición.
     */
    public function getFolioAttribute(): string
    {
        return 'REQ-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Catálogo de estatus correspondiente al tipo de requisición.
     */
    public static function statusesFor(?string $type): array
    {
        return $type === self::TYPE_IMPORT ? self::IMPORT_STATUSES : self::LOCAL_STATUSES;
    }

    /**
     * Color del estatus de arribo.
     */
    public function getArrivalColorAttribute(): string
    {
        return self::ARRIVAL_COLORS[$this->arrival_status] ?? self::ARRIVAL_COLORS['Pendiente'];
    }

    // --- RELACIONES ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /**
     * Hilo de comentarios (tabla polimórfica `comments`).
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable')->orderBy('created_at');
    }

    /**
     * Productos solicitados en la requisición (una lista por requisición).
     */
    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class)->orderBy('id');
    }

    /**
     * Guías de paquetería de las partidas de la requisición.
     */
    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(RequisitionShipment::class, 'requisition_item_shipment', 'requisition_id', 'requisition_shipment_id')
            ->withTimestamps()
            ->distinct();
    }

    /**
     * Recalcula el estatus de arribo de la requisición a partir de sus partidas.
     */
    public function refreshArrivalStatusFromItems(): void
    {
        $statuses = $this->items()->pluck('arrival_status');

        if ($statuses->isEmpty()) {
            return;
        }

        $this->arrival_status = match (true) {
            $statuses->contains('Incidencia') => 'Incidencia',
            $statuses->every(fn ($status) => $status === 'Arribado') => 'Arribado',
            $statuses->contains('En tránsito'), $statuses->contains('Arribado') => 'En tránsito',
            default => 'Pendiente',
        };

        $this->save();
    }
}
