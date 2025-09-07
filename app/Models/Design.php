<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Design extends Model implements HasMedia
{
    // Asegúrate de tener instalado spatie/laravel-medialibrary
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'design_category_id',
        'original_design_id',
    ];

    /**
     * Get the category that owns the design.
     */
    public function designCategory(): BelongsTo
    {
        return $this->belongsTo(DesignCategory::class);
    }

    /**
     * Get the original design from which this one was inspired.
     */
    public function originalDesign(): BelongsTo
    {
        return $this->belongsTo(Design::class, 'original_design_id');
    }

    /**
     * Get the designs that were inspired by this one.
     */
    public function replicas(): HasMany
    {
        return $this->hasMany(Design::class, 'original_design_id');
    }

    /**
     * Get all of the tags for the design.
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }
}