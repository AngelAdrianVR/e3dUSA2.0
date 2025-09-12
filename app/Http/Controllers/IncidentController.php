<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\VacationLog;
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

        $incidentType = IncidentType::find($validatedData['incident_type_id']);

        DB::transaction(function () use ($validatedData, $incidentType) {
            // Eliminar cualquier incidencia existente para ese empleado en esa fecha.
            $existingIncident = Incident::where('employee_detail_id', $validatedData['employee_detail_id'])
                ->where('date', $validatedData['date'])
                ->first();
            
            // Si la incidencia existente era de vacaciones, su log se eliminará en cascada.
            $existingIncident?->delete();

            // Crear la nueva incidencia.
            $incident = Incident::create($validatedData);

            // Si la nueva incidencia es de tipo "Vacaciones", crear el log correspondiente.
            if ($incidentType->name === 'Vacaciones') {
                VacationLog::create([
                    'employee_detail_id' => $incident->employee_detail_id,
                    'incident_id' => $incident->id, // Vincular el log a la incidencia
                    'type' => 'taken',
                    'days' => -1, // Restar un día
                    'description' => 'Día de vacaciones registrado desde nómina.',
                    'date' => $incident->date,
                    'created_by' => auth()->id(),
                ]);
            }
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
        DB::transaction(function () use ($incident) {
            // Cargar la relación para asegurarse de que la tenemos.
            $incident->load('vacationLog');

            // Si existe un log de vacaciones asociado, eliminarlo explícitamente.
            // Aunque el 'cascade' debería funcionar a nivel de BD, esto lo hace a prueba de fallos a nivel de aplicación.
            if ($incident->vacationLog) {
                $incident->vacationLog->delete();
            }

            // Finalmente, eliminar la incidencia.
            $incident->delete();
        });

        return back()->with('success', 'Incidencia removida correctamente.');
    }
}
