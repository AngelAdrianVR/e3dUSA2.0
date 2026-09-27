<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProjectTask extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'project_id', 'title', 'description',
        'start_date', 'due_date', 'finished_at',
        'status', 'assigned_to', 'created_by', 'position',
        'time_spent_seconds', 'timer_started_at', 'completion_notes',
        'rating', 'rating_note', 'rated_by', 'rated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'finished_at' => 'date',
        'timer_started_at' => 'datetime',
        'time_spent_seconds' => 'integer',
        'rating' => 'integer',
        'rated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Responsable de la tarea (siempre miembro del proyecto).
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que calificó el desempeño de la tarea (siempre el creador del proyecto).
     */
    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /**
     * Comentarios de la tarea (con menciones).
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class)->orderBy('created_at');
    }

    /**
     * Archivos/imágenes de la tarea (Spatie Media Library).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')->useDisk('public');

        // Evidencia de finalización: solo visible para los Administradores del proyecto
        $this->addMediaCollection('evidence')->useDisk('public');
    }

    // ===================== Tiempo invertido =====================

    /**
     * ¿El cronómetro está corriendo? (la tarea está En proceso)
     */
    public function getIsTimerRunningAttribute(): bool
    {
        return $this->timer_started_at !== null && $this->status === 'En proceso';
    }

    /**
     * Tiempo total invertido en segundos (acumulado + la sesión en curso).
     */
    public function getTotalTimeSecondsAttribute(): int
    {
        $total = (int) $this->time_spent_seconds;

        if ($this->timer_started_at && $this->status === 'En proceso') {
            // diffInSeconds con $absolute = true (Carbon 3 devuelve valores firmados)
            $total += max(0, (int) $this->timer_started_at->diffInSeconds(now(), true));
        }

        return $total;
    }

    /**
     * Arranca el cronómetro (al pasar a 'En proceso').
     */
    public function startTimer(): void
    {
        if ($this->timer_started_at === null) {
            $this->timer_started_at = now();
        }
    }

    /**
     * Detiene el cronómetro y acumula la sesión (al salir de 'En proceso').
     */
    public function stopTimer(): void
    {
        if ($this->timer_started_at !== null) {
            $this->time_spent_seconds = (int) $this->time_spent_seconds
                + max(0, (int) $this->timer_started_at->diffInSeconds(now(), true));
            $this->timer_started_at = null;
        }
    }

    /**
     * ¿Ya tiene evidencia de finalización cargada?
     */
    public function hasEvidence(): bool
    {
        return $this->hasMedia('evidence') || $this->media()->where('collection_name', 'evidence')->exists();
    }
}
