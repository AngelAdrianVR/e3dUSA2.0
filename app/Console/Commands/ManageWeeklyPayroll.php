<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payroll;
use App\Models\EmployeeDetail;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class ManageWeeklyPayroll extends Command
{
    protected $signature = 'app:manage-weekly-payroll';
    protected $description = 'Cierra el periodo de nómina semanal actual, guarda incidencias autodetectadas y crea el siguiente.';

    public function handle()
    {
        $this->info('Iniciando la gestión de periodos de nómina...');
        Log::info('Iniciando la gestión de periodos de nómina...');

        $currentPayroll = Payroll::where('status', 'Abierta')->latest('start_date')->first();

        if ($currentPayroll) {
            $this->info("Procesando nómina abierta #{$currentPayroll->id}...");
            Log::info("Procesando nómina abierta #{$currentPayroll->id}...");
            $this->saveAutoDetectedIncidents($currentPayroll);

            $currentPayroll->status = 'Cerrada';
            $currentPayroll->save();
            $this->info("Periodo de nómina #{$currentPayroll->id} ha sido cerrado.");
            Log::info("Periodo de nómina #{$currentPayroll->id} ha sido cerrado.");

            $newStartDate = Carbon::parse($currentPayroll->end_date)->addDay();
        } else {
            $this->warn('No se encontró un periodo de nómina abierto. Se creará el primer periodo.');
            $newStartDate = Carbon::now()->next(Carbon::FRIDAY);
        }

        $newEndDate = $newStartDate->copy()->addDays(6);
        $newPayroll = Payroll::create([
            'week_number' => $newStartDate->weekOfYear,
            'start_date' => $newStartDate->toDateString(),
            'end_date' => $newEndDate->toDateString(),
            'status' => 'Abierta',
        ]);

        $this->info("Nuevo periodo de nómina #{$newPayroll->id} (Semana {$newPayroll->week_number}) creado.");
        $this->info('Gestión de periodos de nómina completada.');
        Log::info("Nuevo periodo de nómina #{$newPayroll->id} (Semana {$newPayroll->week_number}) creado.");
        Log::info('Gestión de periodos de nómina completada.');
        return 0;
    }

    private function saveAutoDetectedIncidents(Payroll $payroll)
    {
        $this->info('Detectando y guardando incidencias automáticas (Faltas y Descansos)...');
        Log::info('Detectando y guardando incidencias automáticas (Faltas y Descansos)...');
        $employees = EmployeeDetail::all();
        $unjustifiedAbsenceType = IncidentType::where('name', 'Falta injustificada')->first();
        $dayOffType = IncidentType::where('name', 'Descanso')->first();

        if (!$unjustifiedAbsenceType || !$dayOffType) {
            $this->error('No se encontraron los tipos de incidencia "Falta injustificada" o "Descanso". Abortando guardado.');
            return;
        }

        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);
        $incidentsCreated = 0;

        foreach ($employees as $employee) {
            foreach ($period as $date) {
                $dayString = $date->toDateString();
                Carbon::setLocale('es');
                $dayName = ucfirst($date->isoFormat('dddd'));
                $workDayConfig = collect($employee->work_days)->firstWhere('day', $dayName);

                // Verificar si ya existe una asistencia o incidencia para este día
                $hasAttendance = Attendance::where('employee_detail_id', $employee->id)->whereDate('timestamp', $dayString)->exists();
                $hasIncident = Incident::where('employee_detail_id', $employee->id)->where('date', $dayString)->exists();

                if ($hasAttendance || $hasIncident) {
                    continue;
                }

                $incidentTypeId = null;

                // Detectar Falta Injustificada
                if ($workDayConfig && $workDayConfig['works']) {
                    $incidentTypeId = $unjustifiedAbsenceType->id;
                }
                // Detectar Descanso
                elseif ($workDayConfig && !$workDayConfig['works']) {
                    $incidentTypeId = $dayOffType->id;
                }

                if ($incidentTypeId) {
                    Incident::create([
                        'employee_detail_id' => $employee->id,
                        'payroll_id' => $payroll->id,
                        'incident_type_id' => $incidentTypeId,
                        'date' => $dayString,
                        'comments' => 'Incidencia registrada automáticamente por el sistema.',
                    ]);
                    $incidentsCreated++;
                }
            }
        }
        $this->info("Se han guardado {$incidentsCreated} incidencias automáticamente.");
        Log::info("Se han guardado {$incidentsCreated} incidencias automáticamente.");
    }
}