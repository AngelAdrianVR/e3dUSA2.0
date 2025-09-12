<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SampleTrackingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sample_tracking_id',
        'itemable_id',
        'itemable_type',
        'quantity',
        'requires_modification',
        'feedback', //retroalimentacion del cliente
        'notes', //notas que el vendedor agrega al seguimiento
    ];

    protected $casts = [
        'requires_modification' => 'boolean',
    ];

    // --- RELACIONES ---

    public function sampleTracking(): BelongsTo
    {
        return $this->belongsTo(SampleTracking::class);
    }

    /**
     * Define la relación polimórfica inversa.
     * Un ítem puede ser un CatalogProduct o un NewProductProposal.
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
