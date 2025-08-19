<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['status'];

    /**
     * Define la relación polimórfica inversa.
     * Una tarea tiene una entrada de calendario.
     */
    public function calendarEntry(): MorphOne
    {
        return $this->morphOne(CalendarEntry::class, 'entryable');
    }
}