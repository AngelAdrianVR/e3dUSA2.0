<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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

    public static function canManageArrivals(User $user): bool
    {
        return $user->hasAnyPermission(self::MANAGE_ARRIVALS);
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
}
