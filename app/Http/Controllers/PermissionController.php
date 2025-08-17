<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Permission; // Tu modelo de Permiso
use Spatie\Permission\Models\Role; // Importamos el modelo Role
use Illuminate\Support\Facades\Log; // Opcional: para registrar errores

class PermissionController extends Controller
{
    /**
     * Almacena un nuevo permiso y lo asigna al rol de super administrador.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'module' => 'required|string|max:255',
        ]);

        // Creamos el permiso y lo guardamos en una variable
        $permission = Permission::create($request->all());

        // --- Lógica agregada ---
        // Buscamos el rol de super administrador y le asignamos el nuevo permiso.
        try {
            $superAdminRole = Role::findByName('Super Administrador'); // Asegúrate de que el nombre del rol sea 'Super Administrador' o el que uses
            if ($superAdminRole) {
                $superAdminRole->givePermissionTo($permission);
            }
        } catch (\Exception $e) {
            // Opcional: Si el rol no existe, puedes registrar un error.
            Log::warning("El rol 'Super Administrador' no fue encontrado. El nuevo permiso '{$permission->name}' no fue asignado automáticamente.");
        }
        // --- Fin de la lógica agregada ---

        return back()->with('success', 'Permiso creado y asignado al Super Admin correctamente.');
    }

    /**
     * Actualiza un permiso existente.
     * La actualización se refleja automáticamente en todos los roles y usuarios.
     */
    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
            'module' => 'required|string|max:255',
        ]);

        $permission->update($request->all());

        // --- Aclaración sobre la lógica ---
        // No es necesario agregar código adicional aquí.
        // Al actualizar el nombre del permiso, el paquete spatie/laravel-permission
        // mantiene la relación a través del ID. Por lo tanto, todos los roles y usuarios
        // que tenían el permiso antiguo ahora tendrán automáticamente el permiso con el nuevo nombre.
        // El rol "Super Administrador" ya tiene la relación y esta se actualiza sola.
        // --- Fin de la aclaración ---

        return back()->with('success', 'Permiso actualizado correctamente.');
    }

    /**
     * Elimina un permiso.
     * El permiso se revoca automáticamente de todos los roles y usuarios.
     */
    public function destroy(Permission $permission)
    {
        // --- Aclaración sobre la lógica ---
        // Al eliminar el permiso, el paquete spatie/laravel-permission (o las
        // restricciones de la base de datos) se encarga de eliminar
        // automáticamente las asignaciones de este permiso en las tablas pivote
        // (role_has_permissions y model_has_permissions).
        // Por lo tanto, el permiso se quita de TODOS los roles (incluido el Super Administrador)
        // y usuarios que lo tuvieran. No se necesita código extra.
        // --- Fin de la aclaración ---

        $permission->delete();

        return back()->with('success', 'Permiso eliminado correctamente.');
    }
}