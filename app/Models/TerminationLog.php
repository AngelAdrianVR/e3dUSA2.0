<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TerminationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'termination_date',
        'reason',
        'terminated_by',
        'reinstated_at',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'reinstated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function terminator()
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }
}
