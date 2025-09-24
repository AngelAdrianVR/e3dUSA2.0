<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany; // Importar MorphMany
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Branch extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = [
        'id',
        'rfc',
        'name',
        'status',
        'address',
        'sat_type',
        'password',
        'meet_way',
        'post_code',
        'sat_method',
        'parent_branch_id',
        'days_to_reactive',
        'account_manager_id', // vendedor asignado (usuario)
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'last_purchase_date',
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
     * Obtiene todas las notas asociadas con el cliente.
     */
    public function notes()
    {
        // Ordena las notas por la más reciente.
        return $this->hasMany(BranchNote::class)->latest();
    }

    /**
     * Una sucursal puede tener muchas ventas.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
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

    /**
     * Obtiene todos los contactos de la sucursal (relación polimórfica).
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
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
        return $this->belongsToMany(Product::class, 'branch_suggested_products');
    }

    // ==============
    // ACCESORS
    // ==============

    /**
     * Obtiene la fecha de la última compra de tipo 'venta'.
     * El valor se calcula al momento, asegurando que siempre esté actualizado.
     * Devuelve un objeto Carbon o null.
     */
    public function getLastPurchaseDateAttribute()
    {
        $lastSale = $this->sales()
                         ->where('type', 'venta')
                         ->latest('created_at') // Ordena por la fecha de creación para obtener la más reciente
                         ->select('created_at') // Selecciona solo la columna necesaria para optimizar
                         ->first();

        return $lastSale ? $lastSale->created_at : null;
    }
}
