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
                'roles' => Role::with('permissions:id,name')->get(),
                'permissions' => Permission::all()->groupBy('module'),
            ]);
        }

        public function store(Request $request)
        {
            $request->validate(['name' => 'required|string|max:255|unique:roles,name']);
            Role::create(['name' => $request->name]);
            return back()->with('success', 'Rol creado correctamente.');
        }

        public function update(Request $request, Role $role)
        {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
                'permissions' => 'array'
            ]);

            $role->update(['name' => $request->name]);
            $role->syncPermissions($request->input('permissions', []));

            return back()->with('success', 'Rol actualizado correctamente.');
        }

        public function destroy(Role $role)
        {
            if ($role->name === 'Super Administrador') {
                return back()->with('error', 'No se puede eliminar el rol de Super Administrador.');
            }
            $role->delete();
            return back()->with('success', 'Rol eliminado correctamente.');
        }
    }
    