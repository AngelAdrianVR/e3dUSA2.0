<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $casts = [
        'mentioned_user_ids' => 'array',
    ];

    // Relación: Un comentario pertenece a un usuario.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Usuarios mencionados en el comentario.
     */
    public function mentionedUsers()
    {
        return User::whereIn('id', $this->mentioned_user_ids ?? [])->get();
    }

    // Relación: Un comentario puede pertenecer a diferentes modelos (Oportunidad, Tarea, etc.).
    public function commentable()
    {
        return $this->morphTo();
    }
}
