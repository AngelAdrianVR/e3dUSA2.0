<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTaskComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_task_id', 'user_id', 'body', 'mentioned_user_ids', 'read_by',
    ];

    protected $casts = [
        // Usuarios mencionados en el comentario (recibos de lectura)
        'mentioned_user_ids' => 'array',
        // [{ user_id, read_at }, ...]
        'read_by' => 'array',
    ];

    /**
     * Tarea a la que pertenece el comentario.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    /**
     * Autor del comentario.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
