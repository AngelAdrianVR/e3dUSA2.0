<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function store(Request $request)
    {
        // Lógica para guardar una nueva incidencia
        // Validar request, etc.
        // Se implementará en el siguiente paso.
        return back()->with('success', 'Incidencia registrada.');
    }

    public function destroy($incident)
    {
        // Lógica para eliminar una incidencia
        // Se implementará en el siguiente paso.
        return back()->with('success', 'Incidencia eliminada.');
    }
}