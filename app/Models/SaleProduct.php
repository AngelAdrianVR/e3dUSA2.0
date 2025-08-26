<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
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

    /** Un producto de venta puede tener muchas órdenes de producción */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }
}