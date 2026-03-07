<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Quote extends Model implements Auditable
{
    use AuditableTrait;
    
    protected $fillable = [
        'id', 'status', 'receiver', 'department', 'currency', 'tooling_cost',
        'is_tooling_cost_stroked', 'is_freight_cost_stroked', 'freight_option',
        'freight_cost', 'first_production_days', 'notes', 'rejection_reason',
        'customer_responded_at', 'authorized_by_user_id', 'authorized_at',
        'is_spanish_template', 'show_breakdown', 'created_by_customer',
        'has_early_payment_discount', 'early_payment_discount_amount',
        'early_paid_at', 'branch_id', 'user_id', 'sale_id',
        'root_quote_id', 'version', 'is_active',
    ];

    protected $casts = [
        'tooling_cost' => 'string', 
        'is_tooling_cost_waived' => 'boolean', 
        'is_tooling_cost_stroked' => 'boolean', 
        'is_freight_cost_stroked' => 'boolean', 
        'freight_cost' => 'decimal:2', 
        'customer_responded_at' => 'datetime',
        'authorized_at' => 'datetime',
        'is_spanish_template' => 'boolean',
        'show_breakdown' => 'boolean',
        'created_by_customer' => 'boolean',
        'has_early_payment_discount' => 'boolean',
        'early_payment_discount_amount' => 'decimal:2',
        'early_paid_at' => 'datetime',
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['total_data', 'utility_data'];

    const VAT_RATE = 0.16;

    // ------------------ RELACIONES ------------------

    /**
     * Relación Clásica BelongsToMany (Opcional mantenerla para compatibilidad)
     * NOTA: Esta relación SOLO traerá productos reales del catálogo.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'quote_products')
            ->using(QuoteProduct::class)
            ->withPivot([
                'id', 'quantity', 'unit_price', 'notes',
                'customization_details', 'customer_approval_status', 'show_image',
                'custom_name', 'custom_cost' // Añadidos por si usas la pivote
            ])
            ->withTimestamps();
    }

    /**
     * NUEVA RELACIÓN RECOMENDADA: HasMany a las líneas de cotización.
     * Esta traerá TODOS los productos (del catálogo Y los custom/temporales).
     */
    public function quoteProducts(): HasMany
    {
        return $this->hasMany(QuoteProduct::class);
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function authorizedBy(): BelongsTo { return $this->belongsTo(User::class, 'authorized_by_user_id'); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function rootQuote(): BelongsTo { return $this->belongsTo(Quote::class, 'root_quote_id'); }
    
    public function allVersions(): HasMany
    {
        return $this->hasMany(Quote::class, 'root_quote_id', 'root_quote_id')->orderBy('version', 'asc');
    }

    public function activeVersion(): HasMany
    {
        return $this->hasMany(Quote::class, 'root_quote_id', 'root_quote_id')->where('is_active', true);
    }
    
    // ------------------ ACCESORS & MUTATORS ------------------

    public function getTotalDataAttribute(): array
    {
        // 1. Calcula el subtotal base usando las LINEAS de cotización (HasMany)
        // Así incluye tanto productos de catálogo como personalizados.
        $subtotal = $this->quoteProducts()
            ->where('customer_approval_status', 'Aprobado') // ya no es wherePivot
            ->sum(DB::raw('quantity * unit_price'));

        $totalBeforeDiscount = $subtotal;

        $cleanToolingCost = str_replace(',', '', (string)$this->tooling_cost);
        if (!$this->is_tooling_cost_stroked && is_numeric($cleanToolingCost)) {
            $totalBeforeDiscount += (float) $cleanToolingCost;
        }

        $cleanFreightCost = str_replace(',', '', (string)$this->freight_cost);
        if ($this->freight_option && !$this->is_freight_cost_stroked && is_numeric($cleanFreightCost)) {
            $totalBeforeDiscount += (float) $cleanFreightCost;
        }

        $discountAmount = 0;
        $totalAfterDiscount = $totalBeforeDiscount;

        if ($this->has_early_payment_discount && $this->early_payment_discount_amount > 0) {
            $discountAmount = ($totalBeforeDiscount * $this->early_payment_discount_amount) / 100;
            $totalAfterDiscount = $totalBeforeDiscount - $discountAmount;
        }

        $vatAmount = $totalAfterDiscount * self::VAT_RATE;
        $totalWithVat = $totalAfterDiscount + $vatAmount;

        return [
            'subtotal' => (float) $subtotal,
            'total_before_discount' => (float) $totalBeforeDiscount,
            'discount_percentage' => $this->has_early_payment_discount ? (float) $this->early_payment_discount_amount : 0,
            'discount_amount' => (float) $discountAmount,
            'total_after_discount' => (float) $totalAfterDiscount,
            'vat_amount' => (float) $vatAmount,
            'total_with_vat' => (float) $totalWithVat,
        ];
    }

    public function getUtilityDataAttribute(): array
    {
        $total_sale = 0;
        $total_cost = 0;

        // Iteramos sobre quoteProducts (las líneas). Requiere eager loading: $quote->load('quoteProducts.product');
        foreach ($this->quoteProducts as $line) {
            $quantity = $line->quantity;
            $total_sale += $quantity * $line->unit_price;
            
            // Usamos el Accesor dinámico que creamos en QuoteProduct (toma costo de catalogo o costo temporal)
            $total_cost += $quantity * $line->unit_cost; 
        }

        $final_sale = $total_sale;
        if ($this->has_early_payment_discount && $this->early_payment_discount_amount > 0) {
            $discountAmount = ($total_sale * $this->early_payment_discount_amount) / 100;
            $final_sale = $total_sale - $discountAmount;
        }

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