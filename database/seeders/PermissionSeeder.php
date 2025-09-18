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
            'Super Admin' => ['Ver costos de productos', 'Ver cantidades de dinero', 'Descargar reporte de precios'],
            'Generales' => ['Chatear'],
            'Nóminas' => ['Ver nominas', 'Editar nominas'],
            'Solicitudes de tiempo adicional' => ['Ver solicitudes de tiempo adicional', 'Crear solicitudes de tiempo adicional', 'Gestionar solicitudes de tiempo adicional'],
            'Quioscos' => ['Gestionar quioscos'],
            'Personal' => ['Ver personal', 'Crear personal', 'Editar personal'],
            'Roles' => ['Ver roles y permisos', 'Crear roles y permisos', 'Editar roles y permisos', 'Eliminar roles y permisos'],
            'Bonos' => ['Ver bonos', 'Crear bonos', 'Editar bonos', 'Eliminar bonos'],
            'Descuentos' => ['Ver descuentos', 'Crear descuentos', 'Editar descuentos', 'Eliminar descuentos'],
            'Días festivos' => ['Ver dias festivos', 'Crear dias festivos', 'Editar dias festivos', 'Eliminar dias festivos'],
            'Historial de acciones' => ['Ver historial de acciones'],
            'Tutoriales y manuales' => ['Ver tutoriales y manuales', 'Crear tutoriales y manuales', 'Editar tutoriales y manuales', 'Eliminar tutoriales y manuales'],
            'Maquinaria' => ['Ver maquinas', 'Crear maquinas', 'Editar maquinas', 'Eliminar maquinas'],
            'Mantenimiento' => ['Ver mantenimientos', 'Crear mantenimientos', 'Editar mantenimientos', 'Eliminar mantenimientos', 'Validar mantenimiento de maquinas'],
            'Refacciones' => ['Ver refacciones', 'Crear refacciones', 'Editar refacciones', 'Eliminar refacciones'],
            'Clientes' => ['Ver clientes', 'Crear clientes', 'Editar clientes', 'Eliminar clientes'],
            'Catálogo de productos' => ['Ver catalogo de productos', 'Crear catalogo de productos', 'Editar catalogo de productos', 'Eliminar catalogo de productos'],
            'Materia prima' => ['Ver materia prima', 'Crear materia prima', 'Editar materia prima', 'Eliminar materia prima'],
            'Insumos' => ['Ver insumos', 'Crear insumos', 'Editar insumos', 'Eliminar insumos'],
            'Productos obsoletos' => ['Ver obsoletos', 'Crear obsoletos', 'Editar obsoletos', 'Eliminar obsoletos'],
            'Scrap' => ['Ver scrap', 'Crear scrap', 'Editar scrap', 'Eliminar scrap'],
            'Costo de producción' => ['Ver costos de produccion', 'Crear costos de produccion', 'Editar costos de produccion', 'Eliminar costos de produccion'],
            'Cotizaciones' => ['Ver cotizaciones', 'Crear cotizaciones', 'Editar cotizaciones', 'Eliminar cotizaciones', 'Autorizar cotizaciones', 'Descuentos cotizaciones', 'Utilidad cotizaciones', 'Ver todas las cotizaciones'],
            'Ordenes de venta' => ['Ver ordenes de venta', 'Crear ordenes de venta', 'Editar ordenes de venta', 'Eliminar ordenes de venta', 'Ver todas las ventas', 'Ver utilidad ventas', 'Autorizar ordenes de venta'],
            'Ordenes de producción' => ['Ver ordenes de produccion', 'Crear ordenes de produccion', 'Editar ordenes de produccion', 'Eliminar ordenes de produccion', 'Ver todas las ordenes de produccion'],
            'Envíos' => ['Ver envios'],
            'Facturas' => ['Ver facturas', 'Crear facturas', 'Editar facturas', 'Eliminar facturas'],
            'Proveedores' => ['Ver proveedores', 'Crear proveedores', 'Editar proveedores', 'Eliminar proveedores'],
            'Compras' => ['Ver ordenes de compra', 'Crear ordenes de compra', 'Editar ordenes de compra', 'Eliminar ordenes de compra', 'Autorizar ordenes de compra', 'Ver todas las compras'],
            'Diseño' => ['Ver ordenes de diseño', 'Crear ordenes de diseño', 'Editar ordenes de diseño', 'Eliminar ordenes de diseño', 'Ver todas las ordenes de diseño', 'Asignar diseños', 'Autorizar ordenes de diseño'],
            'Autorización de diseño' => ['Ver formatos de autorizacion de diseño', 'Crear formatos de autorizacion de diseño', 'Editar formatos de autorizacion de diseño', 'Eliminar formatos de autorizacion de diseño', 'Autorizar formatos de autorizacion de diseño'],
            'Análisis de ventas' => ['Ver analisis de ventas'],
            'Seguimiento de muestras' => ['Ver muestras', 'Crear muestras', 'Editar muestras', 'Eliminar muestras'],
            'Almacén' => ['Ver almacenes', 'Crear almacenes', 'Crear movimientos de stock'],
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