<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ClientContactPermissionSeeder extends Seeder
{
    /**
     * Permiso para autorizar clientes con contactos repetidos.
     * Se ejecuta con: php artisan db:seed --class=ClientContactPermissionSeeder
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'Autorizar contactos de clientes', 'guard_name' => 'web'],
            ['module' => 'Clientes']
        );

        $superAdminRole = Role::where('name', 'Super Administrador')->first();
        if ($superAdminRole && !$superAdminRole->hasPermissionTo($permission)) {
            $superAdminRole->givePermissionTo($permission);
        }
    }
}
