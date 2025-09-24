<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany; // 1. Importar MorphMany
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Supplier extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'rfc',
        'nickname',
        'address',
        'post_code',
        'phone',
        'email',
        'notes',
    ];

    /**
     * Obtiene todos los contactos del proveedor (relación polimórfica).
     */
    public function contacts(): MorphMany // 2. Cambiar HasMany por MorphMany
    {
        // 3. Apuntar al modelo Contact y definir el nombre de la relación 'contactable'
        return $this->morphMany(Contact::class, 'contactable');
    }

    /**
     * Get the bank accounts for the supplier.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(SupplierBankAccount::class);
    }

    /**
     * Get the purchases made to this supplier.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * The products that belong to the supplier.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->withPivot('last_price', 'supplier_sku', 'min_quantity')
            ->withTimestamps();
    }

    /**
     * Get the favored products for the supplier.
     */
    public function favoredProducts(): HasMany
    {
        return $this->hasMany(FavoredProduct::class);
    }
}
