<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\ChMessage;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Obtenemos el término de búsqueda y los filtros existentes
        $filters = $request->only('search');

        $users = User::query()
            // Aplicamos el filtro solo si 'search' tiene un valor (búsqueda desde la vista)
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('id', 'like', "%{$search}%");

                    // Permite buscar por "activo" o "inactivo"
                    if (strtolower($search) === 'activo') {
                        $subQuery->orWhere('is_active', 1);
                    } elseif (strtolower($search) === 'inactivo') {
                        $subQuery->orWhere('is_active', 0);
                    }
                });
            })
            ->latest() // Opcional: ordenar por los más recientes
            ->paginate(30)
            // Importante: mantiene los parámetros de búsqueda en los enlaces de paginación
            ->withQueryString(); 

        return inertia('User/Index', [
            'users' => $users,
            'filters' => $filters, // Pasamos los filtros a la vista
        ]);
    }

    public function create()
    {
        $employee_number = User::orderBy('id', 'desc')->first()->id + 1;
        // $bonuses = Bonus::where('is_active', 1)->get();
        // $discounts = Discount::where('is_active', 1)->get();
        // $roles = Role::all();

        return inertia('User/Create', compact('employee_number'));
    }

    public function changeStatus(Request $request, User $user)
    {
        if ($user->is_active) {
            // Antes de desactivar, busca todos los clientes (branches) asignados
            // a este usuario y elimina la asignación (pone el ID en null).
            Branch::where('account_manager_id', $user->id)->update(['account_manager_id' => null]);

            // Ahora, procede a desactivar al usuario
            $user->update([
                'is_active' => false,
                'disabled_at' => $request->disabled_at,
            ]);
        } else {
            // Lógica para reactivar al usuario (sin cambios aquí)
            $user->update([
                'is_active' => true,
                'disabled_at' => null,
            ]);
        }

        // Puedes agregar un return con un mensaje de éxito si lo deseas
        // return back()->with('success', 'Estado del usuario actualizado correctamente.');
    }

    public function getUnseenMessages()
    {
        $unseen_messages = ChMessage::where('to_id', auth()->id())->where('seen', 0)->get()->count();

        return response()->json(['count' => $unseen_messages]);
    }
}
