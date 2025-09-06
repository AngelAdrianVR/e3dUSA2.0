<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_id',
        'product_id',
        'description',
        'quantity',
        'type', // muestra o venta
        'notes',
        // 'unit', // unidad de medida
        'unit_price',
        'additional_stock', // a favor
        'plane_stock', // en avion
        'ship_stock', // en barco
        'total_price',
        'recieved_quantity',
    ];

    /**
     * Get the purchase that owns the item.
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the product associated with the purchase item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
