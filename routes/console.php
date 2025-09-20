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

// Otorga las vacaciones proporcionales a cada empleado activo.
Schedule::command('app:grant-weekly-vacations')->weeklyOn(5, '01:00'); // Cada viernes a la 1:00 AM

// Ejecuta el backup de la base de datos todos los días a la 1:00 AM.
Schedule::command('app:backup-database')->daily()->at('01:00');

// Ejecuta el backup de los medios los días 1 y 15 de cada mes a las 2:00 AM.
Schedule::command('app:backup-media')->twiceMonthly(1, 15, '02:00');

// Revisa las facturas vencidas y manda una notificacion al creador. se ejecuta diario a la 1:00 AM.
Schedule::command('invoices:check-overdue')->daily()->at('01:00');
