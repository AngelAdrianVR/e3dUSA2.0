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
    protected $appends = ['total_data', 'utility_data'];

    // Define la tasa de IVA (16%)
    const VAT_RATE = 0.16;

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
     * @return float
     */
    public function getTotalDataAttribute(): array
    {
        // 1. Calcula el subtotal base de los productos aprobados.
        $subtotal = $this->products()
            ->wherePivot('customer_approval_status', 'Aprobado')
            ->sum(DB::raw('quote_products.quantity * quote_products.unit_price'));

        // 2. Suma costos adicionales (herramental y flete).
        $totalBeforeDiscount = $subtotal;
        if (!$this->is_tooling_cost_stroked) {
            $totalBeforeDiscount += $this->tooling_cost;
        }
        if ($this->freight_option && !$this->is_freight_cost_stroked) {
            $totalBeforeDiscount += $this->freight_cost;
        }

        // 3. Calcula el descuento si aplica.
        $discountAmount = 0;
        $totalAfterDiscount = $totalBeforeDiscount;

        if ($this->has_early_payment_discount && $this->early_payment_discount_amount > 0) {
            // El descuento se calcula sobre el total antes de impuestos.
            $discountAmount = ($totalBeforeDiscount * $this->early_payment_discount_amount) / 100;
            $totalAfterDiscount = $totalBeforeDiscount - $discountAmount;
        }

        // 4. Calcula el IVA sobre el total ya con descuento.
        $vatAmount = $totalAfterDiscount * self::VAT_RATE;
        $totalWithVat = $totalAfterDiscount + $vatAmount;

        // 5. Devuelve todos los valores en un array.
        return [
            'subtotal' => (float) $subtotal,
            'total_before_discount' => (float) $totalBeforeDiscount,
            'discount_percentage' => $this->has_early_payment_discount ? (float) $this->early_payment_discount_amount : 0,
            'discount_amount' => (float) $discountAmount,
            'total_after_discount' => (float) $totalAfterDiscount, // Este es el total sin IVA
            'vat_amount' => (float) $vatAmount,
            'total_with_vat' => (float) $totalWithVat, // Este es el total con IVA
        ];
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

        // Itera sobre los productos para calcular venta y costo base.
        // Es importante que la relación 'products' venga precargada (eager loaded).
        foreach ($this->products as $product) {
            $quantity = $product->pivot->quantity;
            $total_sale += $quantity * $product->pivot->unit_price;
            $total_cost += $quantity * $product->cost;
        }

        $final_sale = $total_sale;
        // Si hay descuento, se recalcula la venta final.
        if ($this->has_early_payment_discount && $this->early_payment_discount_amount > 0) {
            $discountAmount = ($total_sale * $this->early_payment_discount_amount) / 100;
            $final_sale = $total_sale - $discountAmount;
        }

        // La utilidad se calcula sobre la venta final (con descuento).
        $profit = $final_sale - $total_cost;
        $percentage = $total_cost > 0 ? ($profit / $total_cost) * 100 : 0;

        return [
            'total_sale' => (float) $final_sale,
            'total_cost' => (float) $total_cost,
            'profit' => (float) $profit,
            'percentage' => (float) $percentage,
        ];
    }
}
