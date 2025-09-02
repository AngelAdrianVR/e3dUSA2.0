<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_id',
        'user_id',
        'type', // 'pausa', 'reanudacion', 'progreso', 'alerta'
        'notes', 
    ];

    protected $guarded = ['id'];

    // --- RELACIONES ---

    /** Un log pertenece a una orden de producción */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    /** Un log es creado por un usuario */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
