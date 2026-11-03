<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StockMovementPermissionSeeder extends Seeder
{
    /**
     * Permiso para eliminar movimientos de inventario (revirtiendo el stock).
     * Se ejecuta con: php artisan db:seed --class=StockMovementPermissionSeeder
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => 'Eliminar movimientos de stock', 'guard_name' => 'web'],
            ['module' => 'Almacén']
        );

        $superAdminRole = Role::where('name', 'Super Administrador')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permission);
        }

        $almacenistaRole = Role::where('name', 'Almacenista')->first();
        if ($almacenistaRole) {
            $almacenistaRole->givePermissionTo($permission);
        }
    }
}
