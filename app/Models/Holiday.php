<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Holiday extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'name',
        'date',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date'
    ];
}
