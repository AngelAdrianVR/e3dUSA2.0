<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'quantity_to_produce', // la cantidad que hay que producir (es para logica de descuentos de stock)
        'price',
        'is_new_design',
        'customization_details',
        'notes',
        'quantity_produced',
        'quantity_shipped',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'customization_details' => 'array',
    ];

    // --- RELACIONES ---

    /** Un producto de venta pertenece a una venta */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Un producto de venta se refiere a un producto de catálogo */
    public function product(): BelongsTo
    {
        // Asegúrate de tener un modelo Product
        return $this->belongsTo(Product::class);
    }

    /** * Un producto de venta tiene UNA orden de producción.
     * La lógica principal usa esta relación (HasOne) para verificar si ya existe una orden.
     */
    public function production(): HasOne
    {
        return $this->hasOne(Production::class);
    }

    /** * Un producto de venta puede tener MUCHAS órdenes de producción (para re-trabajos, etc.).
     * Mantenemos esta relación para futura escalabilidad.
     */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }
}