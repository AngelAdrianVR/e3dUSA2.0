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
    protected $appends = ['total_estimated_time_minutes']; // Agregamos el nuevo accesor

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    // --- ACCESORES Y MUTADORES ---

    /**
     * Accesor para obtener el tiempo total estimado de producción.
     * Suma los tiempos estimados de todas las tareas asociadas.
     */
    public function totalEstimatedTimeMinutes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->tasks()->sum('estimated_time_minutes'),
        );
    }

    // --- MÉTODOS ---
    
    /**
     * Actualiza el estatus de la producción basado en el estatus de sus tareas.
     * La lógica sigue las reglas especificadas.
     */
    public function updateStatusFromTasks(): void
    {
        if ($this->tasks->isEmpty()) {
            return;
        }

        $taskStatuses = $this->tasks->pluck('status');
        $totalTasks = $taskStatuses->count();
        $newStatus = $this->status;

        // Prioridad 1: "Sin material" (bloqueante, máxima prioridad)
        if ($taskStatuses->contains('Sin material')) {
            $newStatus = 'Sin material';
        }
        // Prioridad 2: Si TODAS las tareas están "Terminada"
        elseif ($this->tasks->where('status', 'Terminada')->count() === $totalTasks) {
            $newStatus = 'Terminada';
        }
        // Prioridad 3: Si AL MENOS UNA tarea está "En Proceso" (la producción está activa)
        elseif ($taskStatuses->contains('En Proceso')) {
            $newStatus = 'En Proceso';
        }
        // Prioridad 4: Si todas las tareas no finalizadas están en pausa (es decir, no hay 'Pendiente' o 'En Proceso')
        elseif ($taskStatuses->every(fn($status) => in_array($status, ['Pausada', 'Terminada']))) {
            $newStatus = 'Pausada';
        }
        // Prioridad 5: Si se ha iniciado alguna tarea (no es 'Pendiente') y no cumple las condiciones anteriores
        // (Ej: mezcla de Pendiente y Terminada), la producción está "En proceso".
        elseif ($taskStatuses->some(fn($status) => $status !== 'Pendiente')) {
            $newStatus = 'En proceso';
        }
        
         // Asignamos el nuevo estatus al modelo para que isDirty() lo detecte si cambia.
        $this->status = $newStatus;

        // --- Lógica para actualizar las fechas ---

        // Si al menos una tarea está "En Proceso" y no se ha registrado la fecha de inicio, la establecemos.
        if ($taskStatuses->contains('En Proceso') && is_null($this->started_at)) {
            $this->started_at = now();
        }

        // Si el nuevo estatus es "Terminada" y no se ha registrado la fecha de fin, la establecemos.
        if ($newStatus === 'Terminada' && is_null($this->finished_at)) {
            $this->finished_at = now();

            // Si la venta es de tipo "stock" O "venta", se procede a agregar al inventario.
            if ($this->saleProduct->sale->type === 'stock' || $this->saleProduct->sale->type === 'venta') {
                $product = $this->saleProduct->product;
                // Priorizamos las unidades buenas, si no, la cantidad que se mandó a producir.
                $quantityToAdd = $this->good_units ?? $this->quantity_to_produce;

                if ($product && ($quantityToAdd > 0)) {
                    // Busca el registro de stock para el producto o lo crea con 0 si no existe.
                    $storage = $product->storages()->firstOrCreate(
                        [], // Busca el primer registro existente sin condiciones específicas.
                        ['quantity' => 0] // Si no existe, lo crea con cantidad inicial 0.
                    );

                    // Incrementa la cantidad de forma atómica para evitar condiciones de carrera.
                    $storage->increment('quantity', $quantityToAdd);
                    
                    // IMPORTANTE: Solo se crea el movimiento de stock si el tipo es 'stock'.
                    // if ($this->saleProduct->sale->type === 'stock') {
                        StockMovement::create([
                            'product_id' => $product->id,
                            'storage_id' => $storage->id,
                            'quantity_change' => $quantityToAdd,
                            'type' => 'Entrada',
                            'notes' => 'Entrada por orden terminada ' . $this->saleProduct->sale->type === 'stock' ? 'OS-' : 'OV-' . $this->saleProduct->sale->id
                        ]);
                    // }
                }
            }
        }

        // --- Guardar cambios si es necesario ---
        if ($this->isDirty()) { // isDirty() revisa si algún atributo del modelo ha cambiado.
            $this->save();

            // Si el estatus fue lo que cambió, le decimos a la venta a la que pertenece 
            // que re-evalúe su propio estado general.
            if ($this->wasChanged('status')) {
                $this->saleProduct->sale->updateStatus();
            }
        }
    }

    // --- RELACIONES ---

    /** Una orden de producción pertenece a un producto de una venta */
    public function saleProduct(): BelongsTo
    {
        return $this->belongsTo(SaleProduct::class);
    }

    /** Una orden de producción es asignada a un operador (User) */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
    
    public function sale(): HasOneThrough
    {
        return $this->hasOneThrough(Sale::class, SaleProduct::class, 'id', 'id', 'sale_product_id', 'sale_id');
    }

    /** Una orden de producción fue creada por un usuario (supervisor) */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Una orden de producción tiene un historial de logs */
    public function logs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    /** Una orden de producción tiene muchas tareas */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}