<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProductExchange extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'sale_id',
        'user_id',
        'returned_product_id',
        'returned_quantity',
        'new_product_id',
        'new_quantity',
        'price_difference',
        'reason',
    ];

    // Relación con la Venta original
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // Usuario que autorizó/creó el cambio
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Producto que entra (Devolución)
    public function returnedProduct()
    {
        return $this->belongsTo(Product::class, 'returned_product_id');
    }

    // Producto que sale (Nuevo)
    public function newProduct()
    {
        return $this->belongsTo(Product::class, 'new_product_id');
    }

    // Definir colección de media para evidencias (opcional, para organización)
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence')
            ->useDisk('public'); // O 's3' si usas nube
    }
}