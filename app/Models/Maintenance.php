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
        'cost',
        'actions',
        'problems',
        'machine_id',
        'responsible',
        'validated_at',
        'validated_by',
        'spare_parts_used', // ids de refacciones usadas en el mantenimiento
        'maintenance_date',
        'maintenance_type',
    ];
    
    protected $casts = [
        'maintenance_date' => 'date',
        'validated_at' => 'datetime',
        'spare_parts_used' => 'array',
    ];

    // relationships
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }
}
