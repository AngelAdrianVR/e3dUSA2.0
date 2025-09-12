<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class SampleTracking extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'status', // 'Pendiente', 'Autorizado', 'Enviado', 'Aprobado', 'Rechazado', 'Devuelto', 'Completado', 'Modificación'
        'branch_id',
        'contact_id',
        'requester_user_id',
        'authorized_by_user_id',
        'sale_id',
        'will_be_returned',
        'expected_devolution_date',
        'comments',
        'authorized_at',
        'denied_at',
        'approved_at',
        'sent_at',
        'returned_at',
        'completed_at',
    ];

    protected $casts = [
        'will_be_returned' => 'boolean',
        'expected_devolution_date' => 'date',
        'authorized_at' => 'datetime',
        'denied_at' => 'datetime',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'returned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // --- RELACIONES ---

    public function items(): HasMany
    {
        return $this->hasMany(SampleTrackingItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    public function contact(): BelongsTo
    {
        // Asumiendo que tienes un modelo Contact
        return $this->belongsTo(Contact::class);
    }

    public function branch(): BelongsTo
    {
        // Asumiendo que tienes un modelo branch
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        // Asumiendo que tienes un modelo sale
        return $this->belongsTo(Sale::class);
    }
}