<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Rutas para el recurso de Nóminas (Payrolls)
    Route::get('payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
    Route::get('payrolls/{payroll}', [PayrollController::class, 'show'])->name('payrolls.show');
    
    // Aquí puedes agregar más rutas como store, update, destroy para payrolls si las necesitas.
    // Route::post('payrolls', [PayrollController::class, 'store'])->name('payrolls.store');
    // Route::put('payrolls/{payroll}', [PayrollController::class, 'update'])->name('payrolls.update');

    // Rutas tipo API para gestionar incidencias y asistencias desde el frontend
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::delete('incidents/{incident}', [IncidentController::class, 'destroy'])->name('incidents.destroy');
    Route::put('attendances/update-day', [AttendanceController::class, 'updateDayAttendances'])->name('attendances.update_day');
});
