<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'id',
        'branch_id',
        'name',
        'charge',
        'birthdate',
        'is_primary',
    ];

    /**
     * Obtiene la sucursal a la que pertenece el contacto.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Obtiene los detalles (teléfonos, emails) del contacto.
     */
    public function details()
    {
        return $this->hasMany(ContactDetail::class);
    }
}
