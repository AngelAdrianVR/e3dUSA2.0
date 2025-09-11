<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VacationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_detail_id',
        'incident_id',
        'type',
        'days',
        'description',
        'date',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'days' => 'decimal:2',
    ];

    public function employeeDetail()
    {
        return $this->belongsTo(EmployeeDetail::class);
    }

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}