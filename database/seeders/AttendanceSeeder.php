<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmployeeDetail;
use App\Models\Payroll;
use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Generando asistencias de prueba (con descansos)...');

        $payroll = Payroll::latest('start_date')->first();
        if (!$payroll) {
            $this->command->error('No se encontraron periodos de nómina. Ejecuta primero PayrollSeeder.');
            return;
        }

        Carbon::setLocale('es');

        $employees = EmployeeDetail::all();
        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);

        foreach ($employees as $employee) {
            $workSchedule = $employee->work_days ?? [];
            if (empty($workSchedule)) continue;

            foreach ($period as $date) {
                $dayName = ucfirst($date->isoFormat('dddd'));
                $dayConfig = collect($workSchedule)->firstWhere('day', $dayName);

                if ($dayConfig && ($dayConfig['works'] ?? false)) {
                    $dayString = $date->toDateString();

                    // Simular entrada
                    $entryTime = Carbon::parse($dayString . ' ' . $dayConfig['start_time'])->addMinutes(rand(-30, 30));
                    Attendance::create([
                        'employee_detail_id' => $employee->id,
                        'timestamp' => $entryTime,
                        'type' => 'entry',
                    ]);

                    // --- LÓGICA PARA SIMULAR DESCANSOS ---
                    $breakMinutes = $dayConfig['break_minutes'] ?? 0;
                    $endBreakTime = null; 

                    if ($breakMinutes > 0 && rand(0, 5) > 1) { // 80% de probabilidad de tomar descanso
                        // El descanso empieza entre 3 y 5 horas después de la entrada
                        $startBreakTime = $entryTime->copy()->addHours(rand(3, 5))->addMinutes(rand(0, 30));
                        
                        $scheduledExit = Carbon::parse($dayString . ' ' . $dayConfig['end_time']);
                        if ($startBreakTime->copy()->addMinutes($breakMinutes)->gt($scheduledExit)) {
                            $startBreakTime = $entryTime->copy()->addHours(3);
                        }

                        Attendance::create([
                            'employee_detail_id' => $employee->id,
                            'timestamp' => $startBreakTime,
                            'type' => 'start_break',
                        ]);

                        $endBreakTime = $startBreakTime->copy()->addMinutes($breakMinutes);
                        Attendance::create([
                            'employee_detail_id' => $employee->id,
                            'timestamp' => $endBreakTime,
                            'type' => 'end_break',
                        ]);
                    }
                    // --- FIN LÓGICA DE DESCANSOS ---

                    // Simular salida
                    $exitTime = Carbon::parse($dayString . ' ' . $dayConfig['end_time'])->addMinutes(rand(-30, 30));
                    
                    // La salida debe ser después del descanso (si hubo) o de la entrada
                    $minExitTime = $endBreakTime ?? $entryTime; 
                    if ($exitTime->lt($minExitTime)) {
                        $exitTime = $minExitTime->copy()->addHours(4); // Dar 4h de trabajo post-descanso/entrada
                    }

                    Attendance::create([
                        'employee_detail_id' => $employee->id,
                        'timestamp' => $exitTime,
                        'type' => 'exit',
                    ]);
                }
            }
        }

        $this->command->info('Asistencias de prueba generadas exitosamente.');
    }
}