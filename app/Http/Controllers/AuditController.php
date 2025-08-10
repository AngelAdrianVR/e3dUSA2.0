<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use OwenIt\Auditing\Models\Audit; // Importa el modelo del paquete

class AuditController extends Controller
{
    public function index(Request $request)
    {
        // Filtros para las pestañas (created, updated, deleted)
        $filters = $request->only('event');

        // Hacemos la consulta a la base de datos
        $audits = Audit::with('user:id,name,profile_photo_path') // Traemos la relación con el usuario para obtener su nombre e imagen
            ->latest() // Ordenamos por los más recientes
            ->when($request->input('event'), function ($query, $event) {
                // Filtramos por el evento si se especifica uno
                $query->where('event', $event);
            })
            ->paginate(100) // Paginamos los resultados
            ->withQueryString(); // Mantenemos los filtros en los enlaces de paginación

        return Inertia::render('Audit/Index', [
            'audits' => $audits,
            'filters' => $filters,
        ]);
    }
}