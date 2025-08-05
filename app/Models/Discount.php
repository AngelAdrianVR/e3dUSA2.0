<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Discount extends Model
{
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
