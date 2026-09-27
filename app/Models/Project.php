<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name', 'description', 'budget', 'currency',
        'start_date', 'tentative_end_date', 'actual_end_date',
        'created_by', 'priority',
    ];

    public const PRIORITIES = ['Normal', 'Urgente'];

    protected $casts = [
        'budget' => 'decimal:2',
        'start_date' => 'date',
        'tentative_end_date' => 'date',
        'actual_end_date' => 'date',
    ];

    /**
     * Usuario que creó el proyecto.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Miembros asignados al proyecto con su rol (Colaborador | Administrador) en el pivot.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot('role', 'first_viewed_at')
            ->withTimestamps();
    }

    /**
     * Tareas del proyecto (ordenadas por la posición del Kanban).
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * Archivos/imágenes del proyecto (Spatie Media Library).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')->useDisk('public');
    }

    // ===================== Helpers de acceso =====================

    public function isMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function memberRole(User $user): ?string
    {
        $member = $this->members()->whereKey($user->id)->first();

        return $member ? $member->pivot->role : null;
    }

    public function isCreator(User $user): bool
    {
        return (int) $this->created_by === (int) $user->id;
    }

    /**
     * ¿Es Administrador del proyecto? (rol dentro del proyecto, creador o Super Administrador).
     * Estos usuarios son los únicos que ven el tiempo invertido y la evidencia de las tareas.
     */
    public function isProjectAdmin(User $user): bool
    {
        return $this->isCreator($user)
            || $this->memberRole($user) === 'Administrador'
            || $user->hasRole('Super Administrador');
    }

    /**
     * Marca la primera vez que un miembro abre el proyecto (confirmación visual en el detalle).
     */
    public function markMemberViewed(User $user): void
    {
        if ($this->isCreator($user)) {
            return;
        }

        $member = $this->members()->whereKey($user->id)->first();

        if ($member && $member->pivot->first_viewed_at === null) {
            $this->members()->updateExistingPivot($user->id, ['first_viewed_at' => now()]);
        }
    }

    /**
     * ¿Puede editar los detalles del proyecto / crear y mover tareas?
     * Creador, miembro con rol 'Administrador' o permiso global 'Editar proyectos'.
     */
    public function canEdit(User $user): bool
    {
        return $this->isCreator($user)
            || $this->memberRole($user) === 'Administrador'
            || $user->hasPermissionTo('Editar proyectos');
    }

    /**
     * ¿Puede ver el proyecto? Creador, miembro (cualquier rol) o rol Super Administrador.
     */
    public function canView(User $user): bool
    {
        return $this->isCreator($user)
            || $this->isMember($user)
            || $user->hasRole('Super Administrador');
    }

    /**
     * Avance del proyecto (0-100) en base a tareas terminadas.
     */
    public function progress(): int
    {
        $total = $this->tasks()->count();

        if ($total === 0) {
            return 0;
        }

        $finished = $this->tasks()->where('status', 'Terminada')->count();

        return (int) round(($finished / $total) * 100);
    }
}
