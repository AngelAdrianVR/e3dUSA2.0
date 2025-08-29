<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'account_manager_id', // vendedor asignado (usuario)
        'last_purchase_date',
    ];

    protected $casts = [
        'last_purchase_date' => 'date'
    ];

    // relaciones
    /**
     * Define la relación Muchos a Muchos con Product.
     * Esto nos permite obtener la lista de productos autorizados para este cliente.
     */
    public function products(): BelongsToMany
    {
        // El nombre de la tabla pivote 'branch_product' sigue la convención de Laravel,
        // por lo que no es necesario especificarla.
        return $this->belongsToMany(Product::class);
    }

    /**
     * Define la relación Uno a Muchos con el historial de precios.
     * Esto nos facilita el acceso a los precios especiales del cliente.
     */
    public function priceHistory(): HasMany
    {
        return $this->hasMany(BranchPriceHistory::class);
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

    // ==============
    // MÉTODOS
    // ==============

    /**
     * Busca la última venta de tipo 'venta' para esta sucursal
     * y actualiza el campo 'last_purchase_date'.
     */
    public function setLastPurchaseDate()
    {
        $lastSale = $this->sales()
                         ->where('type', 'venta')
                         ->latest('created_at') // Ordena por la fecha de creación para obtener la más reciente
                         ->first();

        if ($lastSale) {
            $this->last_purchase_date = $lastSale->created_at;
            $this->save();
        }
    }

    // ==============
    // ACCESORS
    // ==============

    /**
     * Obtiene la fecha de la última compra formateada como 'd-M-Y'.
     * Ejemplo: 10-Aug-2025
     */
    public function getFormattedLastPurchaseDateAttribute()
    {
        if ($this->last_purchase_date) {
            return Carbon::parse($this->last_purchase_date)->format('d-M-Y');
        }

        return 'N/A'; // O el valor que prefieras si no hay fecha
    }
}
