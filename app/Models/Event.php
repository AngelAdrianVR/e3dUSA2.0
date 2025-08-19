<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['location', 'conference_link'];

    /**
     * Define la relación polimórfica inversa.
     * Un evento tiene una entrada de calendario.
     */
    public function calendarEntry(): MorphOne
    {
        return $this->morphOne(CalendarEntry::class, 'entryable');
    }

    /**
     * Define la relación con los usuarios participantes a través de la tabla pivote.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_participants')
                    ->withPivot('status', 'comment') // Carga también los datos de la tabla pivote
                    ->withTimestamps();
    }
}