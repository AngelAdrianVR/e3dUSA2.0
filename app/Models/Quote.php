<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Quote extends Model implements Auditable
{
    use AuditableTrait;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'status',
        'receiver',
        'department',
        'currency',
        'tooling_cost',
        'is_tooling_cost_stroked',
        'is_freight_cost_stroked',
        'freight_option',
        'freight_cost',
        'first_production_days',
        'notes',
        'rejection_reason',
        'customer_responded_at',
        'authorized_by_user_id',
        'authorized_at',
        'is_spanish_template',
        'show_breakdown',
        'created_by_customer',
        'has_early_payment_discount',
        'early_payment_discount_amount',
        'early_paid_at',
        'branch_id',
        'user_id',
        'sale_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tooling_cost' => 'decimal:2',
        'is_tooling_cost_waived' => 'boolean',
        'freight_cost' => 'decimal:2',
        'customer_responded_at' => 'datetime',
        'authorized_at' => 'datetime',
        'is_spanish_template' => 'boolean',
        'show_breakdown' => 'boolean',
        'created_by_customer' => 'boolean',
        'has_early_payment_discount' => 'boolean',
        'early_payment_discount_amount' => 'decimal:2',
        'early_paid_at' => 'datetime',
    ];

    // Accesor para obtener el total de la cotizacion. ()
    protected $appends = ['total', 'utility_data'];

    // ------------------ RELACIONES ------------------

    /**
     * Define la relación muchos a muchos con Productos.
     * Se accede a través de la tabla pivote 'quote_products'.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'quote_products')
            ->using(QuoteProduct::class) // Especifica el modelo pivote
            ->withPivot([
                'id', // Incluir el ID del pivote es una buena práctica
                'quantity',
                'unit_price',
                'notes',
                'customization_details',
                'customer_approval_status',
                'show_image'
            ])
            ->withTimestamps();
    }

    /**
     * Relación con la sucursal del cliente (Branch).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relación con el usuario (vendedor) que creó la cotización.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el usuario que autorizó la cotización.
     */
    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }

    /**
     * Relación con la venta generada a partir de la cotización.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
    
    // ------------------ ACCESORS & MUTATORS ------------------

    /**
     * Calcula el costo total de la cotización dinámicamente.
     * Suma solo los productos aprobados por el cliente.
     * ! Agregar iva si se requiere el total con iva o cualquier otra modificación
     * @return float
     */
    public function getTotalAttribute(): float
    {
        $subtotal = $this->products()
            ->wherePivot('customer_approval_status', 'Aprobado')
            ->sum(DB::raw('quote_products.quantity * quote_products.unit_price'));

        $total = $subtotal;

        if (!$this->is_tooling_cost_stroked) {
            $total += $this->tooling_cost;
        }

        if ($this->freight_option && !$this->is_freight_cost_stroked) {
            $total += $this->freight_cost;
        }

        if ($this->has_early_payment_discount) {
            $total -= $this->early_payment_discount_amount;
        }

        return max(0, $total);
    }

    /**
     * --- Calcula la venta, costo, utilidad y porcentaje ---
     * Devuelve un array con todos los datos necesarios para el frontend.
     *
     * @return array
     */
    public function getUtilityDataAttribute(): array
    {
        $total_sale = 0;
        $total_cost = 0;

        // Itera sobre los productos de la cotización para calcular totales.
        // Es importante que la relación 'products' venga precargada (eager loaded).
        foreach ($this->products as $product) {
            $quantity = $product->pivot->quantity;
            $total_sale += $quantity * $product->pivot->unit_price;
            $total_cost += $quantity * $product->cost; // 'cost' viene del modelo Product
        }

        $profit = $total_sale - $total_cost;
        $percentage = $total_sale > 0 ? ($profit / $total_sale) * 100 : 0;

        return [
            'total_sale' => $total_sale,
            'total_cost' => $total_cost,
            'profit' => $profit,
            'percentage' => $percentage,
        ];
    }
}
