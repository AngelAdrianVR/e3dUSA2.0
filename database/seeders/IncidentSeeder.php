<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmployeeDetail;
use App\Models\Payroll;
use App\Models\Incident;
use App\Models\IncidentType;
use Carbon\CarbonPeriod;

class IncidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Generando incidencias de prueba...');

        $payroll = Payroll::latest('start_date')->first();
        if (!$payroll) {
            $this->command->error('No se encontraron periodos de nómina.');
            return;
        }

        $employees = EmployeeDetail::inRandomOrder()->limit(3)->get(); // Tomar 3 empleados al azar
        $incidentTypes = IncidentType::whereNotIn('name', ['Día festivo', 'Descanso'])->get();
        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);

        foreach ($employees as $employee) {
            // Convertimos el periodo a una colección y tomamos una fecha al azar.
            $randomDate = collect($period->toArray())->random();
            $randomIncidentType = $incidentTypes->random();

            Incident::firstOrCreate([
                'employee_detail_id' => $employee->id,
                'payroll_id' => $payroll->id,
                'date' => $randomDate->toDateString(),
            ], [
                'incident_type_id' => $randomIncidentType->id,
                'comments' => 'Incidencia de prueba generada por seeder.',
            ]);
        }
        
        $this->command->info('Incidencias de prueba generadas.');
    }
}