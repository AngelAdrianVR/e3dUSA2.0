<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualiza los precios especiales y de base que tengan un año o mas sin actualizar
Schedule::command('prices:update-annual')->dailyAt('01:00');

// revisa tareas y eventos del calendario y avisa los que esten a un dia de suceder y los que ya estan en el dia asignado
Schedule::command('app:send-calendar-reminders')->dailyAt('06:00');

// revisa cotizaciones que no han tenido respuesta 3 dias o mas despues de haberse creado
Schedule::command('quotations:check-pending')->dailyAt('06:00');

// Cierra la nómina semanal actual y abre la siguiente cada jueves a las 11:55 PM
Schedule::command('app:manage-weekly-payroll')->thursdays()->at('23:55');