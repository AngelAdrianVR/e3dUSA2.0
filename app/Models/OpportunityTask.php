<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpportunityTask extends Model
{
    use HasFactory;

    // Relación: Una tarea pertenece a una oportunidad.
    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }
    
    // Relación: Una tarea es creada por un usuario.
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // Relación: Una tarea está asignada a un usuario.
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
    
    // Relación: Una tarea puede tener muchos comentarios (polimórfica).
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

