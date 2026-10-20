<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RequisitionPermissionSeeder extends Seeder
{
    /**
     * Permisos del módulo de Requisiciones de Material.
     * Se ejecuta con: php artisan db:seed --class=RequisitionPermissionSeeder
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'Requisiciones de compra' => [
                'Ver requisiciones de compra',
                'Crear requisiciones de compra',
                'Editar requisiciones de compra',
                'Autorizar requisiciones de compra',
                'Registrar pagos de requisiciones de compra',
                'Gestionar arribos de requisiciones',
            ],
        ];

        foreach ($permissions as $module => $permissionList) {
            foreach ($permissionList as $permission) {
                Permission::firstOrCreate(
                    ['name' => $permission, 'guard_name' => 'web'],
                    ['module' => $module]
                );
            }
        }

        $allNames = array_merge(...array_values($permissions));

        // El Super Administrador recibe todos los permisos del módulo.
        $superAdminRole = Role::findByName('Super Administrador');
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo(Permission::whereIn('name', $allNames)->get());
        }

        // Asignación inicial sensata por rol existente. Los permisos de
        // autorización y pagos se asignan desde la UI de Roles y Permisos
        // (Gerencia de compras y Pagos/Tesorería respectivamente).
        $roleAssignments = [
            'Compras' => [
                'Ver requisiciones de compra',
                'Crear requisiciones de compra',
                'Editar requisiciones de compra',
                'Gestionar arribos de requisiciones',
            ],
            'Vendedor' => [
                'Ver requisiciones de compra',
                'Crear requisiciones de compra',
            ],
            'Samuel' => [
                'Ver requisiciones de compra',
                'Crear requisiciones de compra',
            ],
            'Jefe de producción' => [
                'Ver requisiciones de compra',
                'Crear requisiciones de compra',
            ],
            'Almacenista' => [
                'Ver requisiciones de compra',
                'Crear requisiciones de compra',
            ],
            'Asistente de director' => [
                'Ver requisiciones de compra',
            ],
        ];

        foreach ($roleAssignments as $roleName => $names) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo(Permission::whereIn('name', $names)->get());
            }
        }
    }
}
