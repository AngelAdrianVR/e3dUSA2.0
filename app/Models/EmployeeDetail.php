<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDetail extends Model
{
    protected $fillable = [
        'week_salary',
        'birthdate',
        'join_date',
        'job_position',
        'department',
        'hours_per_week',
        'work_days',
        'vacations',
        'department_details',
    ];

    protected $casts = [
        'work_days' => 'array',
        'vacations' => 'array',
        'department_details' => 'array',
        'join_date' => 'datetime',
        'birthdate' => 'date',
    ];

    // relaciones --------------------------------
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bonuses()
    {
        return $this->belongsToMany(Bonus::class, 'employee_bonuses');
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'employee_discounts');
    }
}
