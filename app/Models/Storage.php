<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Storage extends Model
{
    protected $fillable = ['quantity', 'location', 'storable_id', 'storable_type'];

    /**
     * Obtiene el modelo padre (Product, etc.) al que pertenece este registro de inventario.
     */
    public function storable(): MorphTo
    {
        return $this->morphTo();
    }
}
