<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'week_number',
        'start_date',
        'end_date',
        'status',
    ];

    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
