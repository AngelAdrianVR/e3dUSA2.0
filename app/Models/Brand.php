<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Brand extends Model implements Auditable
{   
    use AuditableTrait;
    
    protected $fillable = [
        'id',
        'name',
        'is_luxury',
    ];

    // relaciones
    public function products() :HasMany
    {
        return $this->hasMany(Product::class);
    }
}
