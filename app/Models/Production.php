<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_product_id',
        'operator_id',
        'created_by_user_id',
        'quantity_to_produce',
        'status',
        'estimated_time_minutes',
        'started_at',
        'finished_at',
        'good_units',
        'scrap',
        'scrap_reason',
        'notes',
    ];
    
    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    // --- RELACIONES ---

    /** Una orden de producción pertenece a un producto de una venta */
    public function saleProduct(): BelongsTo
    {
        return $this->belongsTo(SaleProduct::class);
    }

    /** Una orden de producción es asignada a un operador (User) */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** Una orden de producción fue creada por un usuario (supervisor) */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Una orden de producción tiene un historial de logs */
    public function logs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}
