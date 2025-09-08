<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmployeeDetail;
use App\Models\Payroll;
use App\Models\Attendance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Generando asistencias de prueba...');

        // Obtenemos el último periodo de nómina para llenarlo con datos
        $payroll = Payroll::latest('start_date')->first();
        if (!$payroll) {
            $this->command->error('No se encontraron periodos de nómina. Ejecuta primero PayrollSeeder.');
            return;
        }

        $employees = EmployeeDetail::all();
        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);

        foreach ($employees as $employee) {
            // Decidimos aleatoriamente si un empleado tendrá asistencias para no saturar todo
            if (rand(0, 1) === 0) continue;

            $workSchedule = $employee->work_days ?? [];
            if (empty($workSchedule)) continue;

            foreach ($period as $date) {
                $dayOfWeek = $date->dayOfWeekIso; // 1 (Lunes) a 7 (Domingo)
                $dayConfig = collect($workSchedule)->firstWhere('day', $dayOfWeek);

                // Si el empleado trabaja este día y no hay una incidencia registrada
                if ($dayConfig && ($dayConfig['works'] ?? false)) {
                    $dayString = $date->toDateString();

                    // Simular entrada entre 7 y 9 AM
                    $entryTime = Carbon::parse($dayString . ' ' . $dayConfig['start_time'])
                                    ->addMinutes(rand(-30, 30));
                    Attendance::create([
                        'employee_detail_id' => $employee->id,
                        'timestamp' => $entryTime,
                        'type' => 'entry',
                    ]);

                    // Simular salida
                    $exitTime = Carbon::parse($dayString . ' ' . $dayConfig['end_time'])
                                   ->addMinutes(rand(-30, 30));
                    
                    // Asegurarnos que la salida sea después de la entrada
                    if ($exitTime->lt($entryTime)) {
                        $exitTime = $entryTime->copy()->addHours(8);
                    }

                    Attendance::create([
                        'employee_detail_id' => $employee->id,
                        'timestamp' => $exitTime,
                        'type' => 'exit',
                    ]);
                }
            }
        }

        $this->command->info('Asistencias de prueba generadas.');
    }
}