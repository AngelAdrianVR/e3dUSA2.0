<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class BranchPriceHistory extends Model implements Auditable
{
    use HasFactory, AuditableTrait;
    
    // El nombre de la tabla es diferente al plural del modelo, lo especificamos
    protected $table = 'branch_price_history';

    protected $fillable = [
        'branch_id', 'product_id', 'price', 'valid_from', 'valid_to',
    ];

    /**
     * Obtiene la sucursal a la que pertenece este precio.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Obtiene el producto al que pertenece este precio.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
