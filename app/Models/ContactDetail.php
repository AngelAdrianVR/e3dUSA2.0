<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactDetail extends Model
{
    protected $fillable = [
        'id',
        'contact_id',
        'type',
        'value',
        'is_primary',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Obtiene el contacto al que pertenece este detalle.
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
