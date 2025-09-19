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

class Invoice extends Model implements HasMedia, Auditable
{
    use HasFactory;
    use InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'sale_id',
        'branch_id',
        'user_id',
        'company_branch_id',
        'folio',
        'amount',
        'currency',
        'installment_number',
        'total_installments',
        'issue_date',
        'due_date',
        'paid_at',
        'status',
        'payment_option',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // --- RELACIONES ---

    /** Una factura pertenece a una Orden de Venta */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Una factura es creada por un Usuario */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Una factura tiene muchos pagos */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    // --- Lógica de Negocio (Helpers) ---

    /**
     * Calcula la suma total de los pagos recibidos para esta factura.
     */
    public function getPaidAmountAttribute(): float
    {
        // El método `sum()` es muy eficiente para esto.
        return $this->payments()->sum('amount');
    }

    /**
     * Calcula el monto pendiente de pago.
     */
    public function getPendingAmountAttribute(): float
    {
        return $this->amount - $this->paid_amount;
    }
}
