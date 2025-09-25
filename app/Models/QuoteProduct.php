<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class QuoteProduct extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quote_products';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'quote_id',
        'product_id',
        'quantity',
        'unit_price',
        'notes',
        'customization_details',
        'show_image', // muestra o no la imagen del producto en la cotización.
        'customer_approval_status', // 'Pendiente', 'Aprobado', 'Rechazado'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'decimal:2',
        'customization_details' => 'array', // para modificaciones especiales
    ];
}

