<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany; // Importar MorphMany
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Branch extends Model implements Auditable, HasMedia
{
    use AuditableTrait, InteractsWithMedia;

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
        // --- NUEVOS CAMPOS BASADOS EN EL EXCEL ---
        'group_name',    // GRUPO
        'business_name', // RAZON SOCIAL
        'bank_account',  // CUENTA BANCO
        'client_number', // NUMERO DE CLIENTE
        // --- DATOS FISCALES (CFDI) ---
        'payment_method',    // Método de pago: PPD o PUE
        'payment_submethod', // Sub-método: 99 X DEFINIR, TRANSFERENCIA o CHEQUES
        'cfdi_use'           // Uso del CFDI
    ];

    /**
     * Registra las colecciones de medios del cliente.
     */
    public function registerMediaCollections(): void
    {
        // Documento de Constancia de Situación Fiscal (CSF)
        $this->addMediaCollection('csf')->useDisk('public');
    }

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
     * Obtiene la sucursal matriz a la que pertenece esta sucursal.
     */
    public function parent()
    {
        return $this->belongsTo(Branch::class, 'parent_branch_id');
    }

    /**
     * Obtiene las sucursales hijas (si esta es una matriz).
     */
    public function children(): HasMany
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
