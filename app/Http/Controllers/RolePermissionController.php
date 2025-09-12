<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionController extends Controller
{
    public function index()
    {
        return Inertia::render('RolePermission/Index', [
            // Carga los roles con sus permisos (solo id y name para optimizar)
            'roles' => Role::with('permissions:id,name')->get(),
            // Carga todos los permisos y los agrupa por el campo 'module'
            'permissions' => Permission::all()->groupBy('module'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255|unique:roles,name']);
        Role::create(['name' => $request->name]);
        return back()->with('success', 'Rol creado correctamente.');
    }

    /**
     * Actualiza el nombre de un rol y/o sincroniza sus permisos.
     * Esta versión mejorada evita que se borren los permisos al actualizar solo el nombre.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            // Valida el nombre solo si se envía en la petición
            'name' => 'sometimes|required|string|max:255|unique:roles,name,' . $role->id,
            // Valida los permisos solo si se envían en la petición
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name', // Asegura que los permisos existan
        ]);

        // Actualiza el nombre del rol solo si se proporcionó en la petición
        if ($request->has('name')) {
            $role->update(['name' => $validated['name']]);
        }

        // Sincroniza los permisos solo si se proporcionaron en la petición
        if ($request->has('permissions')) {
            // Usa el array validado, si no existe, usa un array vacío por seguridad
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role)
    {
        // Evita que el rol de Super Administrador sea eliminado
        if ($role->name === 'Super Administrador') {
            return back()->with('error', 'No se puede eliminar el rol de Super Administrador.');
        }
        $role->delete();
        return back()->with('success', 'Rol eliminado correctamente.');
    }
}
