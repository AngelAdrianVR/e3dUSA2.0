<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryIncrease extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'employee_detail_id',
        'previous_amount',
        'new_amount',
        'notes',
        'increase_date',
        'created_by',
    ];

    protected $casts = [
        'increase_date' => 'date',
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
    ];

    public function employeeDetail()
    {
        return $this->belongsTo(EmployeeDetail::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
