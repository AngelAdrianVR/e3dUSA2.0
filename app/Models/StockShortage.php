<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockShortage extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'sale_product_id',
        'product_id',
        'storage_id',
        'quantity',
        'quantity_pending',
        'is_component',
        'notes',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_pending' => 'decimal:2',
        'is_component' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleProduct(): BelongsTo
    {
        return $this->belongsTo(SaleProduct::class);
    }

    /**
     * Producto (terminado o componente) que quedó pendiente de rebajar.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('quantity_pending', '>', 0);
    }

    /**
     * Folio de la orden que generó el faltante (OV- para ventas, OS- para stock).
     */
    public function getFolioAttribute(): string
    {
        $prefix = match ($this->sale?->type) {
            'stock' => 'OS-',
            'muestra' => 'OM-',
            default => 'OV-',
        };

        return $prefix . str_pad((string) $this->sale_id, 4, '0', STR_PAD_LEFT);
    }
}
