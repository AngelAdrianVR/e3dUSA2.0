<?php

namespace App\Support;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * Agrupa los permisos que gobiernan el módulo de requisiciones.
 *
 * Se aceptan dos juegos de nombres por compatibilidad:
 *  - Los permisos granulares creados por RequisitionPermissionSeeder (Ver/Crear/Editar...).
 *  - Los permisos definidos en la especificación funcional del módulo
 *    (Gestión de almacén, Gestión de compras, Gerencia de compras, Hacer pagos).
 */
class RequisitionPermissions
{
    public const VIEW_ALL = [
        'Ver requisiciones de compra',
        'Gestión de almacén',
        'Gestión de compras',
        'Gerencia de compras',
        'Hacer pagos',
        'Gestionar arribos de requisiciones',
    ];

    public const CREATE = [
        'Crear requisiciones de compra',
    ];

    public const EDIT = [
        'Editar requisiciones de compra',
        'Gestión de compras',
    ];

    public const AUTHORIZE = [
        'Autorizar requisiciones de compra',
        'Gerencia de compras',
    ];

    public const PAY = [
        'Registrar pagos de requisiciones de compra',
        'Hacer pagos',
    ];

    public const MANAGE_ARRIVALS = [
        'Gestionar arribos de requisiciones',
        'Gestión de compras',
        'Gerencia de compras',
    ];

    /**
     * Captura de la cotización en requisiciones de importación.
     */
    public const QUOTE = [
        'Gerencia de compras',
    ];

    /**
     * Roles que siguen la fecha tentativa de arribo de la mercancía.
     */
    public const ARRIVAL_TRACKING_ROLES = [
        'Almacenista',
        'Jefe de producción',
        'Samuel',
    ];

    /**
     * Permiso que habilita el detalle (show) de una requisición.
     * El resto de los usuarios solo accede al listado.
     */
    public const VIEW_DETAILS = [
        'Gestión de compras',
    ];

    /**
     * Permiso que recibe el aviso de cambios en la frecuencia de consumo.
     */
    public const PURCHASING_MANAGEMENT = [
        'Gestión de compras',
    ];

    /**
     * Cualquier permiso del módulo habilita el acceso al listado.
     */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            self::VIEW_ALL,
            self::CREATE,
            self::EDIT,
            self::AUTHORIZE,
            self::PAY,
            self::MANAGE_ARRIVALS,
        )));
    }

    public static function canViewAll(User $user): bool
    {
        return $user->hasAnyPermission(self::VIEW_ALL);
    }

    public static function canCreate(User $user): bool
    {
        return $user->hasAnyPermission(self::CREATE);
    }

    public static function canEdit(User $user): bool
    {
        return $user->hasAnyPermission(self::EDIT);
    }

    public static function canAuthorize(User $user): bool
    {
        return $user->hasAnyPermission(self::AUTHORIZE);
    }

    public static function canRegisterPayments(User $user): bool
    {
        return $user->hasAnyPermission(self::PAY);
    }

    /**
     * Solo la gestión de compras puede abrir el detalle de una requisición.
     */
    public static function canViewDetails(User $user): bool
    {
        return $user->hasAnyPermission(self::VIEW_DETAILS);
    }

    /**
     * URL de la requisición según el acceso del usuario: detalle o listado.
     */
    public static function urlFor(User $user, Requisition $requisition, array $query = []): string
    {
        if (! self::canViewDetails($user)) {
            return route('requisitions.index', $query, false);
        }

        return route('requisitions.show', array_merge(['requisition' => $requisition->id], $query), false);
    }

    public static function canManageArrivals(User $user): bool
    {
        return $user->hasAnyPermission(self::MANAGE_ARRIVALS);
    }

    /**
     * Permite cotizar requisiciones de importación.
     */
    public static function canQuote(User $user): bool
    {
        return $user->hasAnyPermission(self::QUOTE);
    }

    /**
     * Usuarios activos que deben recibir notificaciones de la etapa indicada.
     */
    public static function usersWith(array $permissions): Collection
    {
        return User::permission($permissions)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Usuarios a notificar excluyendo a quien ejecutó la acción.
     */
    public static function usersToNotify(array $permissions, ?User $exclude = null): Collection
    {
        $users = self::usersWith($permissions);

        if ($exclude) {
            $users = $users->reject(fn (User $user) => $user->id === $exclude->id);
        }

        return $users->values();
    }

    /**
     * Usuarios activos que tienen alguno de los roles indicados.
     *
     * Se validan los roles existentes para evitar excepciones cuando el catálogo
     * de roles cambia (p. ej. "Almacén" aún no está creado).
     */
    public static function usersWithRoles(array $roles, ?User $exclude = null): Collection
    {
        $existingRoles = Role::whereIn('name', $roles)->pluck('name')->all();

        if (empty($existingRoles)) {
            return new Collection();
        }

        $users = User::role($existingRoles)->where('is_active', true)->get();

        if ($exclude) {
            $users = $users->reject(fn (User $user) => $user->id === $exclude->id);
        }

        return $users->values();
    }

    /**
     * Responsables de dar seguimiento a la fecha tentativa de arribo.
     */
    public static function arrivalTrackingUsers(?User $exclude = null): Collection
    {
        return self::usersWithRoles(self::ARRIVAL_TRACKING_ROLES, $exclude)
            ->concat(self::usersToNotify(['Gestión de almacén'], $exclude))
            ->unique('id')
            ->values();
    }
}
