<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Discount extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'amount',
    ];

    // relaciones --------------------------------

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeDetail::class, 'employee_discounts');
    }
}
