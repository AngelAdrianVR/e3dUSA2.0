<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Bonus extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'id',
        'name',
        'description',
        'calculation_type',
        'full_time',
        'half_time',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Un bono ahora tiene muchas reglas
    public function rules(): HasMany
    {
        return $this->hasMany(BonusRule::class);
    }

    public function employee_details(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeDetail::class, 'bonus_employee_detail');
    }
}