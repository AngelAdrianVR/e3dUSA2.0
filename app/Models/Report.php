<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'title',
        'description',
        'type',
        'status',
    ];

    /**
     * Obtiene el usuario que creó el reporte.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
