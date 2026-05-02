<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignOrderPause extends Model
{
    use HasFactory;

    protected $fillable = [
        'design_order_id',
        'paused_at',
        'resumed_at',
    ];

    protected $casts = [
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
    ];

    /**
     * Get the design order that the pause belongs to.
     */
    public function designOrder(): BelongsTo
    {
        return $this->belongsTo(DesignOrder::class);
    }
}