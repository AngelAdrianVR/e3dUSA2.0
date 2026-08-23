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
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'finished_at' => 'date',
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
    }
}
