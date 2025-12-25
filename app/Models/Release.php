<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Release extends Model
{
    protected $fillable = [
        'version',
        'title',
        'is_published',
        'published_at'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    // Relación: Una actualización tiene muchos items (pasos)
    public function items(): HasMany
    {
        return $this->hasMany(ReleaseItem::class)->orderBy('order');
    }

    // Relación: Usuarios que ya han visto esta actualización
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'release_user')
                    ->withPivot('read_at');
    }
    
    // Helper para publicar
    public function publish()
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
}