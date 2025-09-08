<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payroll;
use Carbon\Carbon;

class PayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear la nómina de la semana actual
        $this->createPayrollForDate(Carbon::now());
        // Crear las 4 nóminas de semanas pasadas
        for ($i = 1; $i <= 4; $i++) {
            $this->createPayrollForDate(Carbon::now()->subWeeks($i));
        }
    }

    private function createPayrollForDate(Carbon $date)
    {
        $startOfWeek = $date->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $endOfWeek = $date->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        Payroll::create([
            'week_number' => $date->weekOfYear,
            'start_date' => $startOfWeek,
            'end_date' => $endOfWeek,
            'status' => 'Abierta',
        ]);
    }
}