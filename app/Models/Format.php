<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Format extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'name',
        'is_restricted', // Booleano para saber a qué pestaña va
        'description',
    ];

    // Definir colecciones si quieres validaciones extra (opcional)
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('formats')
            ->useDisk('public'); // O s3, según tu config
    }
}