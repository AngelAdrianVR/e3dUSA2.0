<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignAssignmentLog extends Model
{
    use HasFactory;

    /**
     * Indica que el modelo no debe ser sellado con marcas de tiempo.
     *
     * @var bool
     */
    public $timestamps = false;
    
    // Los logs son solo de creación, no de actualización.
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'design_order_id',
        'previous_designer_id',
        'new_designer_id',
        'changed_by_user_id',
        'reason',
        'changed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Get the design order that the log belongs to.
     */
    public function designOrder(): BelongsTo
    {
        return $this->belongsTo(DesignOrder::class);
    }

    /**
     * Get the previous designer.
     */
    public function previousDesigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_designer_id');
    }

    /**
     * Get the new designer.
     */
    public function newDesigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_designer_id');
    }

    /**
     * Get the user who made the change.
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}