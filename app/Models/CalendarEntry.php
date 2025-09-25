<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// ! valorar si es necesario auditar este modelo
// use OwenIt\Auditing\Contracts\Auditable;
// use OwenIt\Auditing\Auditable as AuditableTrait;

class CalendarEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'title',
        'description',
        'start_datetime',
        'end_datetime',
        'is_full_day',
        'user_id',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'is_full_day' => 'boolean',
    ];

    /**
     * Define la relación polimórfica.
     * Una entrada de calendario puede ser un Evento o una Tarea.
     */
    public function entryable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Define la relación con el usuario creador.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}