<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    /**
     * Store a newly created incident in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_detail_id' => 'required|exists:employee_details,id',
            'payroll_id' => 'required|exists:payrolls,id',
            'incident_type_id' => 'required|exists:incident_types,id',
            'date' => 'required|date',
        ]);

        // Usar una transacción para asegurar que la eliminación y creación sean atómicas.
        DB::transaction(function () use ($validatedData) {
            // Primero, eliminar cualquier incidencia existente para ese empleado en esa fecha.
            Incident::where('employee_detail_id', $validatedData['employee_detail_id'])
                ->where('date', $validatedData['date'])
                ->delete();

            // Luego, crear la nueva incidencia.
            Incident::create($validatedData);
        });

        return back()->with('success', 'Incidencia registrada correctamente.');
    }

    /**
     * Remove the specified incident from storage.
     *
     * @param  \App\Models\Incident  $incident
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Incident $incident)
    {
        $incident->delete();

        return back()->with('success', 'Incidencia removida correctamente.');
    }
}