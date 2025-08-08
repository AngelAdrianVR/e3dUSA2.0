<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Bonus extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'name',
        'description',
        'full_time',
        'half_time',
        'is_active',
    ];

    // relaciones --------------------------------
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeDetail::class, 'employee_bonuses');
    }
}
