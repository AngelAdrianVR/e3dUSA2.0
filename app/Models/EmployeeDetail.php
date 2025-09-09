<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
        // Asumo que tienes los modelos Bonus y Discount
        return $this->belongsToMany(Bonus::class);
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class);
    }
    
    // --- NUEVAS RELACIONES ---
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}