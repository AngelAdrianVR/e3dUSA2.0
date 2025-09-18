<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // --- Rol: Auxiliar de produccion ---
        $auxProduccion = Role::create(['name' => 'Auxiliar de producción']);
        $auxProduccion->givePermissionTo([
            'Chatear',
            'Ver ordenes de produccion',
            'Ver solicitudes de tiempo adicional',
            'Crear solicitudes de tiempo adicional',
            'Editar ordenes de produccion',
            'Ver historial de acciones',
            'Ver obsoletos',
            'Ver scrap',
            'Ver almacenes',
            'Ver catalogo de productos',
            'Ver materia prima',
            'Ver insumos',
            'Crear movimientos de stock',
            'Ver tutoriales y manuales',
        ]);

        // --- Rol: Almacenista ---
        $almacenista = Role::create(['name' => 'Almacenista']);
        $almacenista->givePermissionTo([
            'Chatear',
            'Ver materia prima',
            'Crear materia prima',
            'Editar materia prima',
            'Eliminar materia prima',
            'Ver solicitudes de tiempo adicional',
            'Crear solicitudes de tiempo adicional',
            'Ver insumos',
            'Ver historial de acciones',
            'Ver tutoriales y manuales',
            'Crear insumos',
            'Editar insumos',
            'Eliminar insumos',
            'Ver refacciones',
            'Crear refacciones',
            'Editar refacciones',
            'Ver envios',
            'Ver almacenes',
            'Crear almacenes',
            'Ver obsoletos',
            'Crear obsoletos',
            'Ver scrap',
            'Crear scrap',
            'Crear movimientos de stock',
        ]);

        // --- Rol: Asistente de director ---
        $asistenteDirector = Role::create(['name' => 'Asistente de director']);
        $asistenteDirector->givePermissionTo([
            'Chatear',
            'Ver biblioteca de medios',
            'Descargar reporte de precios',
            'Ver cantidades de dinero',
            'Ver costos de productos',
            'Ver personal',
            'Ver historial de acciones',
            'Ver clientes',
            'Ver catalogo de productos',
            'Ver costos de produccion',
            'Ver todas las cotizaciones',
            'Ver ordenes de venta',
            'Ver todas las ventas',
            'Ver ordenes de produccion',
            'Ver todas las ordenes de produccion',
            'Ver envios',
            'Ver facturas',
            'Ver proveedores',
            'Ver ordenes de compra',
            'Ver todas las compras',
            'Ver todas las ordenes de diseño',
            'Ver analisis de ventas',
            'Ver nominas',
        ]);

        // --- Rol: Diseñador ---
        $disenador = Role::create(['name' => 'Diseñador']);
        $disenador->givePermissionTo([
            'Chatear',
            'Ver biblioteca de medios',
            'Ver ordenes de diseño',
            'Crear ordenes de diseño',
            'Editar ordenes de diseño',
            'Ver formatos de autorizacion de diseño',
            'Crear formatos de autorizacion de diseño',
            'Editar formatos de autorizacion de diseño',
            'Ver catalogo de productos',
            'Ver tutoriales y manuales',
        ]);

        // --- Rol: Jefe de producción ---
        $jefeProduccion = Role::create(['name' => 'Jefe de producción']);
        $jefeProduccion->givePermissionTo([
            'Chatear',
            'Ver biblioteca de medios',
            'Ver ordenes de produccion',
            'Crear ordenes de produccion',
            'Editar ordenes de produccion',
            'Eliminar ordenes de produccion',
            'Ver todas las ordenes de produccion',
            'Ver maquinas',
            'Ver mantenimientos',
            'Ver materia prima',
            'Ver insumos',
            'Ver personal',
            'Gestionar solicitudes de tiempo adicional',
            'Ver tutoriales y manuales',
        ]);

        // --- Rol: Mantenimiento ---
        $mantenimiento = Role::create(['name' => 'Mantenimiento']);
        $mantenimiento->givePermissionTo([
            'Chatear',
            'Ver maquinas',
            'Crear maquinas',
            'Editar maquinas',
            'Ver mantenimientos',
            'Crear mantenimientos',
            'Editar mantenimientos',
            'Validar mantenimiento de maquinas',
            'Ver refacciones',
        ]);

        // --- Rol: Recursos humanos ---
        $recursosHumanos = Role::create(['name' => 'Recursos humanos']);
        $recursosHumanos->givePermissionTo([
            'Chatear',
            'Ver nominas',
            'Editar nominas',
            'Ver solicitudes de tiempo adicional',
            'Gestionar solicitudes de tiempo adicional',
            'Gestionar quioscos',
            'Ver personal',
            'Crear personal',
            'Editar personal',
            'Ver bonos',
            'Crear bonos',
            'Editar bonos',
            'Eliminar bonos',
            'Ver descuentos',
            'Crear descuentos',
            'Editar descuentos',
            'Eliminar descuentos',
            'Ver dias festivos',
            'Crear dias festivos',
            'Editar dias festivos',
        ]);

        // --- Rol: Vendedor ---
        $vendedor = Role::create(['name' => 'Vendedor']);
        $vendedor->givePermissionTo([
            'Chatear',
            'Ver clientes',
            'Crear clientes',
            'Editar clientes',
            'Ver catalogo de productos',
            'Ver costos de produccion',
            'Ver cotizaciones',
            'Crear cotizaciones',
            'Editar cotizaciones',
            'Descuentos cotizaciones',
            'Utilidad cotizaciones',
            'Ver ordenes de venta',
            'Crear ordenes de venta',
            'Editar ordenes de venta',
            'Ver analisis de ventas',
            'Ver muestras',
            'Crear muestras',
            'Editar muestras',
            'Ver biblioteca de medios',
        ]);

        // --- Rol: Compras ---
        $compras = Role::create(['name' => 'Compras']);
        $compras->givePermissionTo([
            'Chatear',
            'Ver materia prima',
            'Crear materia prima',
            'Editar materia prima',
            'Ver insumos',
            'Crear insumos',
            'Editar insumos',
            'Ver proveedores',
            'Crear proveedores',
            'Editar proveedores',
            'Ver ordenes de compra',
            'Crear ordenes de compra',
            'Editar ordenes de compra',
            'Autorizar ordenes de compra',
            'Ver todas las compras',
            'Ver biblioteca de medios',
        ]);
    }
}
