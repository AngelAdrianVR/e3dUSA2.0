<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DesignOrder extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_title',
        'specifications',
        'status',
        'is_hight_priority',
        'requester_id',
        'designer_id',
        'design_category_id',
        'design_id',
        'reuse_justification',
        'branch_name',
        'assigned_at',
        'started_at',
        'finished_at',
        'due_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_hight_priority' => 'boolean',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    /**
     * Get the user who requested the design order.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user (designer) assigned to the design order.
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    /**
     * Get the category for the design order.
     */
    public function designCategory(): BelongsTo
    {
        return $this->belongsTo(DesignCategory::class);
    }

    /**
     * Get the final design associated with the order.
     */
    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    /**
     * Get the assignment logs for the design order.
     */
    public function assignmentLogs(): HasMany
    {
        return $this->hasMany(DesignAssignmentLog::class);
    }
}