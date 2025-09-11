<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class NewProductProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'specifications',
        'status',
        'product_id',
        'approved_at',
    ];

    protected $casts = [
        'specifications' => 'array',
        'approved_at' => 'datetime',
    ];

    // --- RELACIONES ---

    /**
     * Define la relación polimórfica.
     * Una propuesta de producto puede estar en muchos seguimientos de muestras.
     */
    public function sampleItems(): MorphMany
    {
        return $this->morphMany(SampleTrackingItem::class, 'itemable');
    }

    /**
     * El producto del catálogo que se creó a partir de esta propuesta.
     */
    public function product(): BelongsTo
    {
        // Asumiendo que tu modelo se llama product
        return $this->belongsTo(product::class);
    }
}