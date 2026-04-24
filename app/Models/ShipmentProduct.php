<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentProduct extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'shipment_id',
        'sale_product_id',
        'quantity',
        'original_quantity', // This field is used to store the original quantity of the product before any adjustments.
        'less_sent_reason', // This field is used to store the reason for sending less quantity than originally ordered, if applicable.
    ];

    protected $guarded = ['id'];

    // --- RELACIONES ---

    /** Este registro pertenece a un envío */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** Este registro se refiere a un producto de una venta */
    public function saleProduct(): BelongsTo
    {
        return $this->belongsTo(SaleProduct::class);
    }
}
