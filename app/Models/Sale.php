<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Sale extends Model implements HasMedia, Auditable
{
    use InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'id',
        'branch_id',
        'quote_id',
        'contact_id',
        'user_id',
        'invoice_id',
        'currency',
        'type',
        'status',
        'oce_name',
        'order_via',
        'notes',
        'promise_date',
        'is_high_priority',
        'total_amount',
        'freight_option',
        'freight_cost',
        'authorized_user_name',
        'authorized_at',
        'shipping_option', // indica cuantas parcialidades tiene la venta

        // campos de facturación
        'pre_invoice_folio', // folio de pre-factura (si aplica)
        'stamped_invoice_folio', // folio de factura timbrada (si aplica)
        'billing_status', // nuevo campo para indicar el estatus de facturación ('Pendiente Pre-factura', 'Pre-facturada', 'Pendiente Timbrado', 'Timbrada')
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'authorized_at' => 'datetime',
        'promise_date' => 'date',
        'is_high_priority' => 'boolean',
    ];

    protected $appends = ['utility_data', 'production_summary'];

    // --- RELACIONES ---

    /** Una venta pertenece a un usuario (quien la creó) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Una venta puede tener muchas facturas.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Muestra la primer factura para ahorrar memoria en index de ventas.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** contacto */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Una venta pertenece a una sucursal */
    public function branch(): BelongsTo
    {
        // Asegúrate de tener un modelo Branch
        return $this->belongsTo(Branch::class);
    }
    
    /** Una venta tiene muchos productos */
    public function saleProducts(): HasMany
    {
        return $this->hasMany(SaleProduct::class);
    }

    /** Una venta tiene muchos envíos */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /**
     * Relación con los cambios de producto (Intercambios/RMA)
     */
    public function productExchanges(): HasMany
    {
        return $this->hasMany(ProductExchange::class);
    }

    public function productions(): HasManyThrough
    {
        return $this->hasManyThrough(Production::class, SaleProduct::class);
    }


    /**
     * Calcula y devuelve los datos de utilidad para la venta.
     *
     * @return array
     */
    public function getUtilityDataAttribute()
    {
        $totalSale = 0;
        $totalCost = 0;

        foreach ($this->saleProducts as $item) {
            // Asegurarse de que el producto y su costo existen
            if ($item->product) {
                $totalSale += $item->quantity * $item->price;
                $totalCost += $item->quantity * $item->product->cost;
            }
        }

        $profit = $totalSale - $totalCost;
        $percentage = ($totalCost > 0) ? ($profit / $totalCost) * 100 : 0;

        return [
            'total_sale' => $totalSale,
            'total_cost' => $totalCost,
            'profit' => $profit,
            'percentage' => $percentage,
        ];
    }

     /**
     * Calcula y devuelve un resumen del estado de producción para la venta.
     */
    public function getProductionSummaryAttribute()
    {
        if ($this->productions->isEmpty()) {
            return [
                'status' => 'Sin Producción',
                'total_productions' => 0,
                'completed_productions' => 0,
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'percentage' => 0,
                'started_at' => null,
                'finished_at' => null,
            ];
        }

        // Obtener la fecha de inicio más temprana y la fecha de finalización más tardía.
        $startedAt = $this->productions->whereNotNull('started_at')->min('started_at');
        $finishedAt = $this->productions->whereNotNull('finished_at')->max('finished_at');

        $statuses = $this->productions->pluck('status');
        $totalProductions = $statuses->count();
        $overallStatus = 'Pendiente'; // Estado por defecto

        // Lógica de prioridad para el estado general
        if ($statuses->contains('Sin material')) {
            $overallStatus = 'Sin material';
        } elseif ($statuses->every(fn ($status) => $status === 'Terminada')) {
            $overallStatus = 'Terminada';
        } elseif ($statuses->contains('En Proceso')) {
            $overallStatus = 'En Proceso';
        } elseif ($statuses->every(fn ($status) => $status === 'Pausada')) {
            $overallStatus = 'Pausada';
        }

        // Cálculo del progreso basado en tareas
        $totalTasks = 0;
        $completedTasks = 0;
        foreach ($this->productions as $production) {
            $totalTasks += $production->tasks->count();
            $completedTasks += $production->tasks->where('status', 'Terminada')->count();
        }

        return [
            'status' => $overallStatus,
            'total_productions' => $totalProductions,
            'completed_productions' => $this->productions->where('status', 'Terminada')->count(),
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'percentage' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ];
    }

    /**
     * START: New Method
     * Checks if the promise date has passed and updates the priority to high.
     */
    public function updatePriorityBasedOnPromiseDate()
    {
        // 1. Check if there is a promise date and priority is not already high
        if (!$this->promise_date || $this->is_high_priority) {
            return;
        }

        // 2. Check if the promise date is in the past
        if (Carbon::parse($this->promise_date)->startOfDay()->isPast()) {
            $this->is_high_priority = true;
            $this->save();
        }
    }

    /**
     * Actualiza el estatus de la venta basado en el estado de sus producciones y envíos.
     *
     * @return void
     */
    public function updateStatus()
    {
        $productions = $this->productions;
        $shipments = $this->shipments;
        
        $newStatus = $this->status; // Por defecto, mantener el estatus actual

        // Criterio 1: "Enviada" - Si hay envíos y TODOS están en estado "Enviado"
        if ($shipments->isNotEmpty() && $shipments->every('status', '==', 'Enviado')) {
            $newStatus = 'Enviada';
        }
        // Criterio 2: Producción Terminada - El estatus depende del tipo de Venta
        elseif ($productions->isNotEmpty() && $productions->every('status', '==', 'Terminada')) {
            // Si el tipo es 'venta', el siguiente paso es preparar el envío.
            if ($this->type === 'venta') {
                $newStatus = 'Preparando Envío';
            } 
            // Si el tipo es 'stock', la producción ha finalizado.
            elseif ($this->type === 'stock') {
                $newStatus = 'Stock Terminado';
            }
        }
        // Criterio 3: "En Producción" - Si hay al menos UNA producción (y no todas están terminadas)
        elseif ($productions->isNotEmpty()) {
            $newStatus = 'En Producción';
        }
        // Criterio 4: "En Proceso" - Si no hay ninguna producción asociada
        elseif ($productions->isEmpty()) {
            $newStatus = 'En Proceso';
        }
        
        // Actualizar y guardar solo si el estatus ha cambiado para ser más eficientes
        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->save();
        }
    }

    // --- SCOPES para filtrar por estatus de facturación ---
     public function scopePendingPreInvoice($query)
    {
        return $query->where('billing_status', 'Pendiente Pre-factura');
    }

    public function scopePreInvoiced($query)
    {
        return $query->where('billing_status', 'Pre-facturada');
    }

    public function scopePendingStamping($query)
    {
        return $query->where('billing_status', 'Pendiente Timbrado');
    }

    public function scopeStamped($query)
    {
        return $query->where('billing_status', 'Timbrada');
    }

    protected static function booted()
    {
        static::created(function ($sale) {
            // 1. Cambiar prospecto a cliente
            if ($sale->branch && $sale->branch->status === 'Prospecto') {
                $sale->branch->update(['status' => 'Cliente']);
            }

            // 2. Disparar notificación de creación de OV para cobranza
            \App\Jobs\NotifyBillingDepartmentJob::dispatch($sale, 'pre_invoice_required');
        });

        // Detectar cambios cuando se actualiza la venta
        static::updated(function ($sale) {
            
            // Lógica existente: Cambio a "En Proceso"
            if ($sale->wasChanged('status') && $sale->status === 'En Proceso') {
                if ($sale->billing_status === 'Pre-facturada') {
                    $sale->updateQuietly(['billing_status' => 'Pendiente Timbrado']);
                    \App\Jobs\NotifyBillingDepartmentJob::dispatch($sale, 'stamping_required');
                }
            }

            // LÓGICA NUEVA: Cambio a "En Producción"
            if ($sale->wasChanged('status') && $sale->status === 'En Producción') {
                
                // Caso 1: Ya existe pre factura, pero NO existe registro de timbrado
                if (!empty($sale->pre_invoice_folio) && empty($sale->stamped_invoice_folio)) {
                    // Opcional: Actualizar a "Pendiente Timbrado" si tus reglas de negocio lo permiten
                    $sale->updateQuietly(['billing_status' => 'Pendiente Timbrado']);
                    
                    \App\Jobs\NotifyBillingDepartmentJob::dispatch($sale, 'production_pending_stamping');
                }
                
                // Caso 2: No existe pre-factura (está vacía o nula)
                elseif (empty($sale->pre_invoice_folio)) {
                    \App\Jobs\NotifyBillingDepartmentJob::dispatch($sale, 'production_no_pre_invoice');
                }
            }

        });
    }
}