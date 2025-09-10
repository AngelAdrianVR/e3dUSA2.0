<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EmployeeDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Obtiene los datos de asistencia de un empleado en una fecha específica.
     */
    public function getForDay(EmployeeDetail $employee, $date)
    {
        $attendances = Attendance::where('employee_detail_id', $employee->id)
            ->whereDate('timestamp', $date)
            ->orderBy('timestamp', 'asc')
            ->get();

        $entry = $attendances->firstWhere('type', 'entry');
        $exit = $attendances->where('type', 'exit')->last();

        $breaks = [];
        $breakStarts = $attendances->where('type', 'start_break')->values();
        $breakEnds = $attendances->where('type', 'end_break')->values();

        for ($i = 0; $i < $breakStarts->count(); $i++) {
            if (isset($breakStarts[$i]) && isset($breakEnds[$i])) {
                $breaks[] = [
                    'start_break' => $breakStarts[$i]->timestamp->format('H:i:s'),
                    'end_break' => $breakEnds[$i]->timestamp->format('H:i:s'),
                ];
            }
        }

        return response()->json([
            'entry' => $entry ? $entry->timestamp->format('H:i:s') : null,
            'exit' => $exit ? $exit->timestamp->format('H:i:s') : null,
            'breaks' => $breaks,
        ]);
    }

    /**
     * Actualiza los registros de asistencia para un empleado en una fecha específica.
     * Este método utiliza una estrategia de "borrar y recrear" para garantizar la consistencia de los datos.
     */
    public function update(Request $request, EmployeeDetail $employee, $date)
    {
        $request->validate([
            'entry' => 'nullable|date_format:H:i:s',
            'exit' => 'nullable|date_format:H:i:s|after_or_equal:entry',
            'breaks.*.start_break' => 'nullable|date_format:H:i:s',
            'breaks.*.end_break' => 'nullable|date_format:H:i:s|after_or_equal:breaks.*.start_break',
        ]);

        try {
            DB::transaction(function () use ($request, $employee, $date) {
                $carbonDate = Carbon::parse($date);

                // 1. Limpiar todos los registros existentes para este día.
                Attendance::where('employee_detail_id', $employee->id)
                    ->whereDate('timestamp', $date)
                    ->delete();

                // 2. Recrear el registro de Entrada si se proporcionó.
                if ($request->filled('entry')) {
                    $entryTime = $request->input('entry');
                    $entryTimestamp = $carbonDate->copy()->setTimeFromTimeString($entryTime);
                    
                    // Calcular retardo.
                    $lateMinutes = 0;
                    $workDayConfig = collect($employee->work_days)
                                     ->firstWhere('day', ucfirst($entryTimestamp->isoFormat('dddd')));

                    if ($workDayConfig && $workDayConfig['works']) {
                        $scheduledStartTime = $carbonDate->copy()->setTimeFromTimeString($workDayConfig['start_time']);
                        if ($entryTimestamp->isAfter($scheduledStartTime)) {
                            $lateMinutes = $scheduledStartTime->diffInMinutes($entryTimestamp);
                        }
                    }

                    Attendance::create([
                        'employee_detail_id' => $employee->id,
                        'timestamp' => $entryTimestamp,
                        'type' => 'entry',
                        'late_minutes' => $lateMinutes,
                        'ignore_late' => false, // El estado de "ignorar" se resetea al editar manualmente.
                    ]);
                }

                // 3. Recrear el registro de Salida si se proporcionó.
                if ($request->filled('exit')) {
                    Attendance::create([
                        'employee_detail_id' => $employee->id,
                        'timestamp' => $carbonDate->copy()->setTimeFromTimeString($request->input('exit')),
                        'type' => 'exit',
                    ]);
                }

                // 4. Recrear los registros de Descansos si se proporcionaron.
                if ($request->has('breaks') && is_array($request->input('breaks'))) {
                    foreach ($request->input('breaks') as $break) {
                        if (!empty($break['start_break']) && !empty($break['end_break'])) {
                            Attendance::create([
                                'employee_detail_id' => $employee->id,
                                'timestamp' => $carbonDate->copy()->setTimeFromTimeString($break['start_break']),
                                'type' => 'start_break',
                            ]);
                            Attendance::create([
                                'employee_detail_id' => $employee->id,
                                'timestamp' => $carbonDate->copy()->setTimeFromTimeString($break['end_break']),
                                'type' => 'end_break',
                            ]);
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('Error al actualizar la asistencia: ' . $e->getMessage());
            return back()->with('error', 'Ocurrió un error inesperado al guardar. Por favor, inténtalo de nuevo.');
        }

        return back()->with('success', 'Registro de asistencia actualizado correctamente.');
    }

    /**
     * Cambia el estado de "ignorar retardo" para un registro de entrada.
     */
    public function toggleIgnoreLate(Attendance $attendance)
    {
        if ($attendance->type === 'entry') {
            $attendance->update(['ignore_late' => !$attendance->ignore_late]);
            return back()->with('success', 'Estado de retardo actualizado.');
        }
        return back()->with('error', 'Operación no válida.');
    }
}