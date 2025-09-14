<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'bonus_id',
        'metric',
        'operator',
        'value',
    ];

    public function bonus()
    {
        return $this->belongsTo(Bonus::class);
    }
}
