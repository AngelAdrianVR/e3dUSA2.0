<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Branch extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'rfc',
        'name',
        'status',
        'address',
        'sat_type',
        'password',
        'meet_way',
        'post_code',
        'sat_method',
        'important_notes',
        'parent_branch_id',
        'days_to_reactive',
        'account_manager_id',
        'last_purchase_date',
    ];

    protected $casts = [
        'last_purchase_date' => 'date'
    ];

    // relaciones
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /**
     * Obtiene la sucursal matriz de esta sucursal (si existe).
     */
    public function parent()
    {
        return $this->belongsTo(Branch::class, 'parent_branch_id');
    }

    /**
     * Obtiene las sucursales hijas.
     */
    public function children()
    {
        return $this->hasMany(Branch::class, 'parent_branch_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Obtiene el vendedor asignado a esta sucursal.
     */
    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * Obtiene todo el historial de precios especiales para esta sucursal.
     */
    public function priceHistory()
    {
        return $this->hasMany(BranchPriceHistory::class);
    }
    
    /**
     * Obtiene el precio especial vigente para un producto específico.
     */
    public function currentPriceFor(Product $product)
    {
        return $this->hasOne(BranchPriceHistory::class)
                    ->where('product_id', $product->id)
                    ->whereNull('valid_to');
    }

    /**
     * Obtiene los productos sugeridos para esta sucursal.
     */
    public function suggestedProducts()
    {
        return $this->belongsToMany(Product::class, 'branch_suggested_products')
                    ->withPivot('sort_order')
                    ->orderBy('sort_order');
    }
}
