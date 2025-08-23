<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualiza los precios especiales y de base que tengan un año o mas sin actualizar
Schedule::command('prices:update-annual')->dailyAt('01:00');
