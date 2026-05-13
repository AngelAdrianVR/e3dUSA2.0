<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Production extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'id',
        'sale_product_id',
        'created_by_user_id',
        'quantity_to_produce',
        'status',
        'started_at',
        'finished_at',
        'good_units',
        'scrap',
        'scrap_reason',
        'notes',
    ];
    
    protected $guarded = ['id'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['total_estimated_time_minutes'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    // --- ACCESORES Y MUTADORES ---

    public function totalEstimatedTimeMinutes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tasks()->sum('estimated_time_minutes'),
        );
    }

    // --- MÉTODOS ---

    /**
     * Registra el avance de piezas y genera los movimientos de stock correspondientes.
     * Esto se llama desde el controlador cuando se pausan o terminan tareas.
     */
    public function registerProductionProgress($goodUnits = 0, $scrap = 0, $scrapReason = null)
    {
        $product = $this->saleProduct->product;
        // Busca el registro de stock para el producto o lo crea con 0 si no existe.
        $storage = $product->storages()->firstOrCreate([], ['quantity' => 0]);

        // 1. Registro de unidades buenas (Parcialidades o Fin de tarea)
        if ($goodUnits > 0) {
            $this->increment('good_units', $goodUnits);

            if (in_array($this->saleProduct->sale->type, ['stock', 'venta'])) {
                $storage->increment('quantity', $goodUnits);
                
                StockMovement::create([
                    'product_id' => $product->id,
                    'storage_id' => $storage->id,
                    'quantity_change' => $goodUnits,
                    'type' => 'Entrada',
                    'notes' => 'Avance/Terminación de orden ' . ($this->saleProduct->sale->type === 'stock' ? 'OS-' : 'OV-') . $this->saleProduct->sale->id
                ]);
            }
        }

        // 2. Registro de Merma (Descuenta del stock y lo guarda)
        if ($scrap > 0) {
            $this->increment('scrap', $scrap);
            
            if ($scrapReason) {
                // Concatena si ya había una razón antes
                $this->scrap_reason = $this->scrap_reason ? $this->scrap_reason . ' | ' . $scrapReason : $scrapReason;
                $this->save();
            }

            if (in_array($this->saleProduct->sale->type, ['stock', 'venta'])) {
                // Prevenir stock negativo: solo descontar la merma posible según el stock actual
                $deductibleScrap = min($scrap, $storage->quantity);

                if ($deductibleScrap > 0) {
                    $storage->decrement('quantity', $deductibleScrap);
                    
                    StockMovement::create([
                        'product_id' => $product->id,
                        'storage_id' => $storage->id,
                        'quantity_change' => $deductibleScrap, // Negativo para salida
                        'type' => 'Salida',
                        'notes' => 'Merma en orden ' . ($this->saleProduct->sale->type === 'stock' ? 'OS-' : 'OV-') . $this->saleProduct->sale->id . ' - Razón: ' . $scrapReason
                    ]);
                }
            }
        }
    }
    
    /**
     * Actualiza el estatus de la producción basado en el estatus de sus tareas.
     */
    public function updateStatusFromTasks(): void
    {
        if ($this->tasks->isEmpty()) {
            return;
        }

        $taskStatuses = $this->tasks->pluck('status');
        $totalTasks = $taskStatuses->count();
        $newStatus = $this->status;

        // Prioridades
        if ($taskStatuses->contains('Sin material')) {
            $newStatus = 'Sin material';
        } elseif ($this->tasks->where('status', 'Terminada')->count() === $totalTasks) {
            $newStatus = 'Terminada';
        } elseif ($taskStatuses->contains('En Proceso')) {
            $newStatus = 'En Proceso';
        } elseif ($taskStatuses->every(fn($status) => in_array($status, ['Pausada', 'Terminada']))) {
            $newStatus = 'Pausada';
        } elseif ($taskStatuses->some(fn($status) => $status !== 'Pendiente')) {
            $newStatus = 'En proceso';
        }
        
        $this->status = $newStatus;

        // Fechas
        if ($taskStatuses->contains('En Proceso') && is_null($this->started_at)) {
            $this->started_at = now();
        }

        if ($newStatus === 'Terminada' && is_null($this->finished_at)) {
            $this->finished_at = now();
        }

        // --- Guardar cambios si es necesario ---
        if ($this->isDirty()) {
            $this->save();

            if ($this->wasChanged('status')) {
                $this->saleProduct->sale->updateStatus();
            }
        }
    }

    // --- RELACIONES ---

    public function saleProduct(): BelongsTo
    {
        return $this->belongsTo(SaleProduct::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
    
    public function sale(): HasOneThrough
    {
        return $this->hasOneThrough(Sale::class, SaleProduct::class, 'id', 'id', 'sale_product_id', 'sale_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}