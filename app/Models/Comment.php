<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    // Relación: Un comentario pertenece a un usuario.
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación: Un comentario puede pertenecer a diferentes modelos (Oportunidad, Tarea, etc.).
    public function commentable()
    {
        return $this->morphTo();
    }
}
