<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Sale extends Model implements HasMedia, Auditable
{
    use InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'branch_id',
        'quote_id',
        'contact_id',
        'user_id',
        'type',
        'status',
        'oce_name',
        'order_via',
        'notes',
        'is_high_priority',
        'total_amount',
        'freight_option',
        'freight_cost',
        'authorized_user_name',
        'authorized_at',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'authorized_at' => 'datetime',
    ];

    protected $appends = ['utility_data'];

    // --- RELACIONES ---

    /** Una venta pertenece a un usuario (quien la creó) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** contacto */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Una venta pertenece a una sucursal */
    public function branch(): BelongsTo
    {
        // Asegúrate de tener un modelo Branch
        return $this->belongsTo(Branch::class);
    }
    
    /** Una venta tiene muchos productos */
    public function saleProducts(): HasMany
    {
        return $this->hasMany(SaleProduct::class);
    }

    /** Una venta tiene muchos envíos */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }


    /**
     * Calcula y devuelve los datos de utilidad para la venta.
     *
     * @return array
     */
    public function getUtilityDataAttribute()
    {
        // Carga la relación si aún no ha sido cargada para evitar problemas N+1
        $this->loadMissing('saleProducts.product');

        $totalSale = 0;
        $totalCost = 0;

        foreach ($this->saleProducts as $item) {
            // Asegurarse de que el producto y su costo existen
            if ($item->product) {
                $totalSale += $item->quantity * $item->price;
                $totalCost += $item->quantity * $item->product->cost;
            }
        }

        $profit = $totalSale - $totalCost;
        $percentage = ($totalSale > 0) ? ($profit / $totalSale) * 100 : 0;

        return [
            'total_sale' => $totalSale,
            'total_cost' => $totalCost,
            'profit' => $profit,
            'percentage' => $percentage,
        ];
    }
}
