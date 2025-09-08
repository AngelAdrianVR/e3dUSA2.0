<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use AuditableTrait;
    use HasRoles;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'active_alerts',
        'disabled_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active_alerts' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // relaciones --------------------------------

    public function employeeDetail()
    {
        return $this->hasOne(EmployeeDetail::class);
    }

    /**
     * Los eventos a los que un usuario ha sido invitado.
     */
    public function attendedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_participants')
            ->withPivot('status', 'comment')
            ->withTimestamps();
    }

    /**
     * Las entradas de calendario (eventos/tareas) que un usuario ha creado.
     */
    public function calendarEntries(): HasMany
    {
        return $this->hasMany(CalendarEntry::class);
    }

    /**
     * Obtiene las sucursales que este usuario gestiona.
     */
    public function managedBranches()
    {
        return $this->hasMany(Branch::class, 'account_manager_id');
    }

    /**
     * Obtiene todas las cotizaciones creadas por este usuario.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'user_id');
    }

    /**
     * Obtiene todas las cotizaciones autorizadas por este usuario.
     */
    public function authorizedQuotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'authorized_by_user_id');
    }

    /**
     * Get the design orders requested by the user.
     */
    public function requestedDesignOrders(): HasMany
    {
        return $this->hasMany(DesignOrder::class, 'requester_id');
    }

    /**
     * Get the design orders assigned to the user (as designer).
     */
    public function assignedDesignOrders(): HasMany
    {
        return $this->hasMany(DesignOrder::class, 'designer_id');
    }


    // ==========================================================
    // ========================== metodos =======================
    /**
     * Agrega o actualiza una alerta activa para el usuario.
     *
     * @param string $key La clave única de la alerta (ej. 'pending_quotations')
     * @param array $alertData Los datos de la alerta
     */
    public function addActiveAlert(string $key, array $alertData): void
    {
        $alerts = $this->active_alerts ?? [];
        $alerts[$key] = $alertData;
        $this->update(['active_alerts' => $alerts]);
    }

    /**
     * Elimina una alerta activa del usuario.
     *
     * @param string $key La clave única de la alerta a eliminar
     */
    public function removeActiveAlert(string $key): void
    {
        $alerts = $this->active_alerts ?? [];
        if (isset($alerts[$key])) {
            unset($alerts[$key]);
            $this->update(['active_alerts' => $alerts]);
        }
    }
}
