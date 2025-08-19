<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class BranchPricing extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'product_id',
        'branch_id',
        'price',
    ];

    // Relaciones
    public function product() :BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch() :BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    
}
