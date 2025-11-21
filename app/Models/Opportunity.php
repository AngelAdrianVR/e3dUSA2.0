<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    use HasFactory;

    // Relación: Una oportunidad es creada por un usuario.
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // Relación: Una oportunidad está asignada a un usuario (vendedor).
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }
    
    // Relación: Una oportunidad puede tener muchas tareas.
    public function tasks()
    {
        return $this->hasMany(OpportunityTask::class);
    }
    
    // Relación: Una oportunidad puede tener muchos comentarios (polimórfica).
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    // Relación: Muchos usuarios pueden estar asociados a una oportunidad.
    public function users()
    {
        return $this->belongsToMany(User::class, 'opportunity_user')->withPivot('role')->withTimestamps();
    }
    
    // Si tienes un modelo Branch
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

