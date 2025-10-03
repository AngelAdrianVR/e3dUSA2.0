<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmployeeDetail;
use App\Models\VacationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GrantWeeklyVacations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:grant-weekly-vacations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Otorga las vacaciones proporcionales semanales a los empleados activos según su antigüedad.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el proceso de otorgamiento de vacaciones semanales...');
        Log::info('Iniciando el proceso de otorgamiento de vacaciones semanales...');

        // Obtenemos todos los detalles de empleados cuyos usuarios están activos.
        $activeEmployees = EmployeeDetail::whereHas('user', function ($query) {
            $query->where('is_active', true);
        })->get();

        if ($activeEmployees->isEmpty()) {
            $this->info('No se encontraron empleados activos. Proceso finalizado.');
            Log::info('No se encontraron empleados activos. Proceso finalizado.');
            return 0;
        }

        $count = 0;
        foreach ($activeEmployees as $employee) {
            $seniorityInYears = Carbon::parse($employee->join_date)->diffInYears(Carbon::now());
            $annualVacationDays = $this->getAnnualVacationDays($seniorityInYears);
            
            // Calculamos el proporcional semanal (un año tiene aprox. 52.14 semanas)
            $weeklyVacationDays = round($annualVacationDays / 52.14, 4);

            VacationLog::create([
                'employee_detail_id' => $employee->id,
                'type'               => 'earned',
                'days'               => $weeklyVacationDays,
                'description'        => "Otorgamiento semanal proporcional ({$annualVacationDays} días anuales).",
                'date'               => Carbon::now()->toDateString(),
                'created_by'         => null, // Proceso automático
            ]);
            $count++;
        }

        $this->info("Se han otorgado vacaciones a {$count} empleados.");
        $this->info('Proceso de otorgamiento de vacaciones finalizado con éxito.');
        Log::info("Se han otorgado vacaciones a {$count} empleados.");
        Log::info('Proceso de otorgamiento de vacaciones finalizado con éxito.');
        return 0;
    }

    /**
     * Devuelve el número de días de vacaciones anuales según los años de servicio cumplidos.
     * Basado en la Ley Federal del Trabajo de México (reforma "Vacaciones Dignas" 2023).
     *
     * @param int $completedYears
     * @return int
     */
    private function getAnnualVacationDays(int $completedYears): int
    {
        if ($completedYears >= 30) return 32;
        if ($completedYears >= 25) return 30;
        if ($completedYears >= 20) return 28;
        if ($completedYears >= 15) return 26;
        if ($completedYears >= 10) return 24;
        if ($completedYears >= 5) return 22;

        // Para los primeros 5 años (0 a 4 años cumplidos)
        return 12 + (2 * $completedYears);
    }
}