<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ProjectPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos del módulo Proyectos
        $permissions = [
            'Proyectos' => [
                'Ver proyectos',
                'Crear proyectos',
                'Editar proyectos',
                'Eliminar proyectos',
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

        // Asignar los permisos nuevos al rol Super Administrador
        $superAdminRole = Role::findByName('Super Administrador');
        if ($superAdminRole) {
            $names = array_merge(...array_values($permissions));
            $superAdminRole->givePermissionTo(Permission::whereIn('name', $names)->get());
        }
    }
}
