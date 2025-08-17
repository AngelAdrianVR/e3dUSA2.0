<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class CompanyBranch extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'name',
        'status', // PROSTECTO / CLIENTE
        'address',
        'is_main',
        'meet_way',
        'sat_type',
        'password',
        'post_code',
        'sat_method',
        'important_notes',
        'parent_branch_id',
        'days_to_reactive',
        'converted_to_client_at',
    ];

    protected $casts = [
        'converted_to_client_at' => 'date'
    ];

    // relaciones
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'branch_pricing')
                    ->withPivot('price')
                    ->withTimestamps();
    }
}
