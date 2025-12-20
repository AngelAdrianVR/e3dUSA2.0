<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ReleaseItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'release_id',
        'module_name',
        'description',
        'order'
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    // Opcional: Definir conversiones de imágenes (thumbnails, optimización)
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('release-images')
             ->singleFile(); // Solo una imagen por slide/item
    }
}