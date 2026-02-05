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
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'status',
        'receiver',
        'department',
        'currency',
        'tooling_cost', // <--- Ya lo tenías fillable, está bien
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

        // --- CAMPOS NUEVOS PARA VERSIONADO ---
        'root_quote_id',
        'version',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // --- CAMBIO 1: 'tooling_cost' ahora es un 'string' ---
        // Ya no intentará convertirlo a decimal, previniendo el error 'Unable to cast value'.
        'tooling_cost' => 'string', 
        'is_tooling_cost_waived' => 'boolean', // ¿Esta línea está en uso? la dejé por si acaso
        'is_tooling_cost_stroked' => 'boolean', // Agregué esta que faltaba en tu $casts original
        'is_freight_cost_stroked' => 'boolean', // Agregué esta que faltaba en tu $casts original
        'freight_cost' => 'decimal:2', // Asumo que freight_cost sigue siendo decimal
        'customer_responded_at' => 'datetime',
        'authorized_at' => 'datetime',
        'is_spanish_template' => 'boolean',
        'show_breakdown' => 'boolean',
        'created_by_customer' => 'boolean',
        'has_early_payment_discount' => 'boolean',
        'early_payment_discount_amount' => 'decimal:2',
        'early_paid_at' => 'datetime',

        // --- CASTS NUEVOS ---
        'version' => 'integer',
        'is_active' => 'boolean',
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

     // --- RELACIONES DE VERSIONADO ---

    /**
     * Relación a la cotización raíz.
     * Para una cotización v3, esto apunta a la v1.
     * Para la v1, esto apunta a sí misma.
     */
    public function rootQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'root_quote_id');
    }

    /**
     * Relación para obtener TODAS las versiones de este hilo.
     * Si estás en la v3, esto te dará la v1, v2, v3, v4...
     */
    public function allVersions(): HasMany
    {
        // Se relaciona usando el root_quote_id de esta instancia.
        return $this->hasMany(Quote::class, 'root_quote_id', 'root_quote_id')
                    ->orderBy('version', 'asc');
    }

    /**
     * Relación para obtener solo la versión activa.
     */
    public function activeVersion(): HasMany
    {
        return $this->hasMany(Quote::class, 'root_quote_id', 'root_quote_id')
                    ->where('is_active', true);
    }
    
    // ------------------ ACCESORS & MUTATORS ------------------

    /**
     * Calcula el costo total de la cotización dinámicamente.
     * Suma solo los productos aprobados por el cliente.
     * @return array
     */
    public function getTotalDataAttribute(): array
    {
        // 1. Calcula el subtotal base de los productos aprobados.
        $subtotal = $this->products()
            ->wherePivot('customer_approval_status', 'Aprobado')
            ->sum(DB::raw('quote_products.quantity * quote_products.unit_price'));

        // 2. Suma costos adicionales (herramental y flete).
        $totalBeforeDiscount = $subtotal;

        // --- CORRECCIÓN TOOLING COST ---
        // Primero eliminamos las comas para evitar errores con formatos tipo "1,500".
        // str_replace devuelve el valor sin comas. Si es "A consultar", sigue siendo "A consultar".
        $cleanToolingCost = str_replace(',', '', (string)$this->tooling_cost);

        // Validamos si el valor limpio es numérico. 
        // "1500" => true. "1500 USD" => false. "A consultar" => false.
        if (!$this->is_tooling_cost_stroked && is_numeric($cleanToolingCost)) {
            $totalBeforeDiscount += (float) $cleanToolingCost;
        }

        // --- CORRECCIÓN FREIGHT COST ---
        // Aplicamos la misma lógica de seguridad para el flete.
        $cleanFreightCost = str_replace(',', '', (string)$this->freight_cost);

        if ($this->freight_option && !$this->is_freight_cost_stroked && is_numeric($cleanFreightCost)) {
            $totalBeforeDiscount += (float) $cleanFreightCost;
        }

        // 3. Calcula el descuento si aplica.
        $discountAmount = 0;
        $totalAfterDiscount = $totalBeforeDiscount;

        if ($this->has_early_payment_discount && $this->early_payment_discount_amount > 0) {
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
            'total_after_discount' => (float) $totalAfterDiscount,
            'vat_amount' => (float) $vatAmount,
            'total_with_vat' => (float) $totalWithVat,
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