<?php

    namespace App\Http\Controllers;

    use Illuminate\Http\Request;
    use App\Models\Permission; // Asegúrate de usar tu modelo personalizado

    class PermissionController extends Controller
    {
        public function store(Request $request)
        {
            $request->validate([
                'name' => 'required|string|max:255|unique:permissions,name',
                'module' => 'required|string|max:255',
            ]);

            Permission::create($request->all());

            return back()->with('success', 'Permiso creado correctamente.');
        }

        public function update(Request $request, Permission $permission)
        {
            $request->validate([
                'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
                'module' => 'required|string|max:255',
            ]);

            $permission->update($request->all());

            return back()->with('success', 'Permiso actualizado correctamente.');
        }

        public function destroy(Permission $permission)
        {
            $permission->delete();

            return back()->with('success', 'Permiso eliminado correctamente.');
        }
    }
    