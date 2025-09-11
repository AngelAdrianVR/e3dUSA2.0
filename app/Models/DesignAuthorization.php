<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DesignAuthorization extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'design_order_id',
        'version',
        'product_name',
        'material',
        'color',
        'production_methods',
        'specifications',
        'logistic_details',
        // 'quantity',
        'responded_at',
        'is_accepted',
        'rejection_reason',
        'authorizer_name',
        'seller_id',
        'branch_id',
        'contact_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'production_methods' => 'array',
        'is_accepted' => 'boolean',
        'responded_at' => 'datetime',
    ];

    /**
     * Relación: Una autorización pertenece a una orden de diseño.
     */
    public function designOrder(): BelongsTo
    {
        return $this->belongsTo(DesignOrder::class);
    }

    /**
     * Relación: Una autorización es gestionada por un vendedor (User).
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Relación: Una autorización pertenece a una sucursal.
     */
    public function branch(): BelongsTo
    {
        // Asegúrate de que el modelo Branch exista
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relación: Una autorización está asociada a un contacto.
     */
    public function contact(): BelongsTo
    {
        // Asegúrate de que el modelo Contact exista
        return $this->belongsTo(Contact::class);
    }
}
