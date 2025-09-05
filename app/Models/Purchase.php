<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'folio',
        'status',
        'supplier_id',
        'user_id',
        'authorizer_id',
        'supplier_contact_id',
        'supplier_bank_account_id',
        'subtotal',
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
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(SupplierContact::class, 'supplier_contact_id');
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
