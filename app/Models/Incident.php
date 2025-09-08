<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_detail_id',
        'payroll_id',
        'incident_type_id',
        'date',
        'comments',
    ];

    public function employeeDetail()
    {
        return $this->belongsTo(EmployeeDetail::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function incidentType()
    {
        return $this->belongsTo(IncidentType::class);
    }
}
