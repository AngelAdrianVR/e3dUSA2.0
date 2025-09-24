<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Purchase extends Model implements HasMedia, Auditable
{
    use HasFactory;
    use InteractsWithMedia, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'rating',
        'status', //Autorizada, Compra realizada, Compra recibida
        'supplier_id',
        'user_id',
        'authorizer_id',
        'supplier_contact_id',
        'supplier_bank_account_id',
        'subtotal',
        // 'type', // Venta, Muestra
        'is_spanish_template',
        'tax',
        'total',
        'currency',
        'notes',
        'shipping_details',
        'emited_at',
        'authorized_at',
        'expected_delivery_date',
        'recieved_at',
        'invoice_folio',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'emited_at' => 'datetime',
        'authorized_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'recieved_at' => 'datetime',
        'is_spanish_template' => 'boolean',
        'rating' => 'array',
    ];

    /**
     * Get the supplier for the purchase.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who created the purchase.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who authorized the purchase.
     */
    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorizer_id');
    }
    
    /**
     * Get the supplier contact for the purchase.
     * * --- CORRECCIÓN ---
     * Se ha cambiado el modelo al que se relaciona de 'SupplierContact' a 'Contact'.
     * Ahora la compra se relaciona correctamente con el nuevo modelo de contactos polimórfico.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_contact_id');
    }

    /**
     * Get the bank account for the purchase.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(SupplierBankAccount::class, 'supplier_bank_account_id');
    }

    /**
     * Get the items for the purchase.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
