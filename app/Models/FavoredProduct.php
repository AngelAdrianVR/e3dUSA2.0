<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoredProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supplier_id',
        'product_id',
        'quantity',
    ];

    /**
     * Get the supplier that owns the favored product.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the product that is favored.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function favoredStockRequests()
    {
        return $this->hasMany(FavoredStockRequest::class);
    }
}
