<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Maintenance extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'problems',
        'actions',
        'cost',
        'maintenance_type',
        'responsible',
        'machine_id',
        'maintenance_date',
        'validated_by',
        'validated_at',
    ];
    
    protected $casts = [
        'maintenance_date' => 'date',
        'validated_at' => 'datetime',
    ];

    // relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
