<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Carbon\Carbon;

class PmsTask extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    // Se agregó 'finished_at' a fillable para permitir su actualización masiva
    protected $fillable = [
        'folio', 'sourceable_type', 'sourceable_id', 'title', 'description', 
        'department', 'origin', 'priority', 'kanban_status', 
        'start_date', 'due_date', 'finished_at', 'responsible_id', 'created_by'
    ];

    // Se castean a datetime para usarlos con date-fns en el frontend
    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'finished_at' => 'datetime', 
    ];

    /**
     * Relación a la tarea original (ProductionTask o Task del calendario)
     */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Colección de Media Library para la evidencia ISO
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence')
             ->useDisk('public'); // O 's3' si usas la nube
    }

    // Scope para filtrar expiradas
    public function scopeExpired($query)
    {
        return $query->where('due_date', '<', Carbon::now())
                     ->whereNotIn('kanban_status', ['Validación', 'Terminado']);
    }

    // Generar un folio automáticamente
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->folio)) {
                $latest = self::latest('id')->first();
                $nextId = $latest ? $latest->id + 1 : 1;
                $model->folio = 'FOLIO:' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
            }
        });
    }
}