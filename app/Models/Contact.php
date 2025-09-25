<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Contact extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'charge',
        'birthdate',
        'is_primary',
    ];

    /**
     * Obtiene el modelo padre al que pertenece el contacto (Branch, Customer, etc.).
     */
    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Obtiene los detalles (teléfonos, emails) del contacto.
     */
    public function details()
    {
        return $this->hasMany(ContactDetail::class);
    }
}
