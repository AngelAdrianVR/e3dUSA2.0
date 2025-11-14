<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FavoredStockRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'favored_product_id',
        'user_id',
        'quantity_requested',
        'shipping_method',
        'quantity_before_request',
        'quantity_after_request',
        'status',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'quantity_requested' => 'decimal:2',
        'quantity_before_request' => 'decimal:2',
        'quantity_after_request' => 'decimal:2',
    ];

    /**
     * Obtener el registro de producto a favor de donde salió.
     */
    public function favoredProduct()
    {
        return $this->belongsTo(FavoredProduct::class);
    }

    /**
     * Obtener el usuario que solicitó.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Obtener el producto principal (a través de favoredProduct).
     */
    public function product()
    {
        // Definir la relación "has one through"
        // Queremos "un" Product "a través de" FavoredProduct
        return $this->hasOneThrough(
            Product::class,
            FavoredProduct::class,
            'id', // Llave foránea en FavoredStockRequest (que apunta a FavoredProduct)
            'id', // Llave foránea en FavoredProduct (que apunta a Product)
            'favored_product_id', // Llave local en FavoredStockRequest
            'product_id' // Llave local en FavoredProduct
        );
    }
}