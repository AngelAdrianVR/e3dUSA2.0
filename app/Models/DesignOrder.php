<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DesignOrder extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'id',
        'order_title',
        'specifications',
        'status',
        'is_hight_priority',
        'requester_id',
        'designer_id',
        'design_category_id',
        'design_id',
        'modifies_design_id',
        'reuse_justification',
        'branch_id',
        'contact_id',
        'assigned_at',
        'started_at',
        'finished_at',
        'due_date',
        'authorized_user_name',
        'authorized_at',
    ];

    protected $casts = [
        'is_hight_priority' => 'boolean',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'due_date' => 'datetime',
        'authorized_at' => 'datetime',
    ];

    // ====================================================
    // ================= Relaciones =======================
    
    public function designAuthorization(): HasOne
    {
        return $this->hasOne(DesignAuthorization::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function designCategory(): BelongsTo
    {
        return $this->belongsTo(DesignCategory::class);
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(DesignAssignmentLog::class);
    }

    /**
     * Historial de pausas de esta orden de diseño.
     */
    public function pauses(): HasMany
    {
        return $this->hasMany(DesignOrderPause::class);
    }

    // ====================================================
    // ================= Métodos y Cálculos ===============

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('design_order_files');
    }

    /**
     * Calcula el tiempo total invertido en segundos, DESCONTANDO las pausas.
     */
    public function getActiveTimeInSeconds(): int
    {
        if (!$this->started_at) {
            return 0;
        }

        // Usar timestamps enteros elimina los errores de desfase de zona horaria de diffInSeconds
        $endTimeTs = $this->finished_at ? $this->finished_at->timestamp : now()->timestamp;
        $startTimeTs = $this->started_at->timestamp;

        // Validar integridad
        if ($endTimeTs < $startTimeTs) {
            return 0;
        }

        $totalRawSeconds = $endTimeTs - $startTimeTs;
        $pausedSeconds = 0;

        foreach ($this->pauses as $pause) {
            if (!$pause->paused_at) continue;

            $pauseStartTs = $pause->paused_at->timestamp;
            $pauseEndTs = $pause->resumed_at ? $pause->resumed_at->timestamp : $endTimeTs;
            
            if ($pauseEndTs > $pauseStartTs) {
                // Prevención de datos corruptos cruzados fuera del tiempo base
                $actualPauseStart = max($pauseStartTs, $startTimeTs);
                $actualPauseEnd = min($pauseEndTs, $endTimeTs);
                
                if ($actualPauseEnd > $actualPauseStart) {
                    $pausedSeconds += ($actualPauseEnd - $actualPauseStart);
                }
            }
        }

        return (int) max(0, $totalRawSeconds - $pausedSeconds);
    }

    /**
     * Comprueba si la orden está actualmente pausada (tiene una pausa sin reanudar).
     */
    public function getIsPausedAttribute(): bool
    {
        return $this->pauses()->whereNull('resumed_at')->exists();
    }
}