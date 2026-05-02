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

        // Si no ha terminado, calculamos el tiempo hasta el momento actual
        $endTime = $this->finished_at ?? now();
        $totalRawSeconds = $endTime->diffInSeconds($this->started_at);

        $pausedSeconds = 0;

        // Sumar todos los segundos que la orden pasó pausada
        foreach ($this->pauses as $pause) {
            // Si la pausa aún no tiene resumed_at, significa que sigue pausada hasta el "endTime"
            $pauseEnd = $pause->resumed_at ?? $endTime;
            
            if ($pauseEnd > $pause->paused_at) {
                $pausedSeconds += $pauseEnd->diffInSeconds($pause->paused_at);
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