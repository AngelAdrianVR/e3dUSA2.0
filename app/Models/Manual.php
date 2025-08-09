<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Manual extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, AuditableTrait;

    protected $fillable = [
        'title',
        'description',
        'type',
        'views',
        'user_id',
    ];

    // relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
