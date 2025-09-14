<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_detail_id',
        'timestamp',
        'type',
        'late_minutes',
        'ignore_late',
        'source',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function employeeDetail()
    {
        return $this->belongsTo(EmployeeDetail::class);
    }
}