<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Definir permisos por módulo
        $permissions = [
            'Usuarios' => ['Ver personal', 'Crear personal', 'Editar personal', 'Eliminar personal'],
            'Bonos' => ['Ver bonos', 'Crear bonos', 'Editar bonos', 'Eliminar bonos'],
            'Descuentos' => ['Ver descuentos', 'Crear descuentos', 'Editar descuentos', 'Eliminar descuentos'],
            'Historial de acciones' => ['Ver historial de acciones'],
            'Roles' => ['Ver roles y permisos', 'Crear roles y permisos', 'Editar roles y permisos', 'Eliminar roles y permisos'],
            'Tutoriales y manuales' => ['Ver tutoriales y manuales', 'Crear tutoriales y manuales', 'Editar tutoriales y manuales', 'Eliminar tutoriales y manuales'],
        ];

        foreach ($permissions as $module => $permissionList) {
            foreach ($permissionList as $permission) {
                Permission::create(['name' => $permission, 'module' => $module]);
            }
        }
        
        // Crear un rol de Super Administrador y asignarle todos los permisos
        $superAdminRole = Role::create(['name' => 'Super Administrador']);
        $superAdminRole->givePermissionTo(Permission::all());

        // --- 2. AÑADE ESTA SECCIÓN ---
        // Asignar el rol de Super Administrador a los usuarios con ID 1, 2 y 3
        $users = User::find([1, 2, 3]);
        foreach ($users as $user) {
            $user->assignRole($superAdminRole);
        }
        // --- FIN DE LA SECCIÓN ---
    }
}