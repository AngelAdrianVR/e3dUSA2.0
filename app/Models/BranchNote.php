<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'user_id',
        'content',
    ];

    // Relación: Una nota pertenece a un usuario (autor)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relación: Una nota pertenece a un cliente (branch)
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Obtiene todas las notas asociadas con el cliente.
     */
    public function notes()
    {
        // Carga automáticamente el usuario (autor) y ordena por la más reciente
        return $this->hasMany(BranchNote::class)->with('user')->latest();
    }
}