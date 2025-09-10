<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use App\Models\EmployeeDetail;
use App\Models\Payroll;

class IncidentController extends Controller
{
    /**
     * Almacena una nueva incidencia en la base de datos.
     * Evita duplicados para un mismo empleado en la misma fecha.
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_detail_id' => 'required|exists:employee_details,id',
            'payroll_id' => 'required|exists:payrolls,id',
            'incident_type_id' => 'required|exists:incident_types,id',
            'date' => 'required|date',
        ]);

        // Evita duplicados: si ya existe una incidencia para ese empleado en esa fecha, no hace nada.
        Incident::firstOrCreate(
            [
                'employee_detail_id' => $request->employee_detail_id,
                'date' => $request->date,
            ],
            [
                'payroll_id' => $request->payroll_id,
                'incident_type_id' => $request->incident_type_id,
            ]
        );

        return redirect()->back()->with('success', 'Incidencia asignada correctamente.');
    }

    /**
     * Elimina una incidencia específica de la base de datos.
     */
    public function destroy(Incident $incident)
    {
        $incident->delete();

        // Redirecciona a la página anterior con un mensaje de éxito. Inertia se encargará de recargar los datos.
        return redirect()->back()->with('success', 'Incidencia eliminada correctamente.');
    }
}