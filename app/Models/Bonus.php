<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bonus extends Model
{
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
