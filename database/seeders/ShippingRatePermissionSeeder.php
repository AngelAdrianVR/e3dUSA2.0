<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShippingRatePermissionSeeder extends Seeder
{
    /**
     * Crea los permisos del módulo de tarifas/envíos y los asigna a los roles
     * que ya tenían acceso a envíos (además del Super Administrador).
     *
     * Es idempotente: se puede correr varias veces sin duplicar registros.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = ['Ver tarifas', 'Gestionar tarifas', 'Editar información de envío'];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'module' => 'Envíos',
            ]);
        }

        $roles = Role::whereHas('permissions', function ($query) {
                $query->whereIn('name', ['Ver envios', 'Ver tarifas']);
            })
            ->orWhere('name', 'Super Administrador')
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }
    }
}
