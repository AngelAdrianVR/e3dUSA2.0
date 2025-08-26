<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'quote_id',
        'contact_id',
        'user_id',
        'type',
        'status',
        'oce_name',
        'order_via',
        'promise_date',
        'notes',
        'is_high_priority',
        'total_amount',
        'freight_option',
        'freight_cost',
        'authorized_user_name',
        'authorized_at',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'authorized_at' => 'datetime',
        'promise_date' => 'date',
    ];

    // --- RELACIONES ---

    /** Una venta pertenece a un usuario (quien la creó) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Una venta pertenece a una sucursal */
    public function branch(): BelongsTo
    {
        // Asegúrate de tener un modelo Branch
        return $this->belongsTo(Branch::class);
    }
    
    /** Una venta tiene muchos productos */
    public function saleProducts(): HasMany
    {
        return $this->hasMany(SaleProduct::class);
    }

    /** Una venta tiene muchos envíos */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
