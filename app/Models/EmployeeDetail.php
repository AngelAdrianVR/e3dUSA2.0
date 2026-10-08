<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class EmployeeDetail extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * Solo se registran las modificaciones de horas hechas desde nómina
     * (evento personalizado "hours_updated"); se desactivan los eventos automáticos.
     */
    protected $auditEvents = [];

    protected $fillable = [
        'user_id',
        'week_salary',
        'birthdate',
        'join_date',
        'job_position',
        'department',
        'hours_per_week',
        'rigid_schedule',
        'work_days',
        'vacations',
        'department_details',
    ];

    protected $casts = [
        'work_days' => 'array',
        'vacations' => 'array',
        'department_details' => 'array',
        'rigid_schedule' => 'boolean',
        'join_date' => 'datetime',
        'birthdate' => 'date',
    ];

    // relaciones --------------------------------
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function overtimeRequests()
    {
        return $this->hasMany(OvertimeRequest::class);
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

    public function salaryIncreases()
    {
        return $this->hasMany(SalaryIncrease::class);
    }
}
