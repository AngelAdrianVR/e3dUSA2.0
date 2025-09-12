<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payroll;
use Carbon\Carbon;

class PayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('Generando periodos de nómina (Viernes - Jueves)...');

        // Buscamos el primer viernes del mes actual para empezar
        $startDate = Carbon::now()->startOfMonth()->next(Carbon::FRIDAY);

        // Creamos 4 periodos de nómina hacia atrás desde esa fecha
        for ($i = 0; $i < 4; $i++) {
            $endDate = $startDate->copy()->addDays(6); // Jueves

            Payroll::create([
                'week_number' => $startDate->weekOfYear,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => $i == 0 ? 'Abierta' : 'Cerrada', // Dejamos la más reciente como abierta
            ]);

            // Retrocedemos al viernes anterior para el siguiente ciclo
            $startDate->subWeek();
        }

        $this->command->info('4 periodos de nómina generados exitosamente.');
    }
}