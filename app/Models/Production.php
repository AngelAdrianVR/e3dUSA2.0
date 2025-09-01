<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Importante agregar esto

class Production extends Model
{
    use HasFactory;

    protected $fillable = [
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
        // Si no hay tareas, no hacemos nada.
        if ($this->tasks->isEmpty()) {
            return;
        }

        $taskStatuses = $this->tasks->pluck('status');
        $totalTasks = $taskStatuses->count();
        $newStatus = $this->status; // Por defecto, mantenemos el estatus actual

        // 1. Si al menos una tarea está "Sin material", la producción es "Sin material" (Máxima prioridad)
        if ($taskStatuses->contains('Sin material')) {
            $newStatus = 'Sin material';
        }
        // 2. Si TODAS las tareas están "Terminada"
        elseif ($this->tasks->where('status', 'Terminada')->count() === $totalTasks) {
            $newStatus = 'Terminada';
        }
        // 3. Si TODAS las tareas están "Pausada"
        elseif ($this->tasks->where('status', 'Pausada')->count() === $totalTasks) {
            $newStatus = 'Pausada';
        }
        // 4. Si al menos una es diferente de "Pendiente" (y no se cumplió ninguna condición anterior)
        elseif ($taskStatuses->contains(fn($status) => $status !== 'Pendiente')) {
            $newStatus = 'En proceso';
        }

        // Actualizamos el estado solo si ha cambiado para evitar escrituras innecesarias en la BD
        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->save();
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