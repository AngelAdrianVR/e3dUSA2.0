<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class BranchVolumePrice extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'branch_id', 'product_id', 'user_id', 'min_quantity', 'max_quantity', 'price', 'currency',
    ];

    protected $casts = [
        'min_quantity' => 'float',
        'max_quantity' => 'float',
        'price' => 'float',
    ];

    /**
     * Obtiene la sucursal a la que pertenece este rango de precio.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Obtiene el producto al que pertenece este rango de precio.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtiene el usuario que registró este rango de precio.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
