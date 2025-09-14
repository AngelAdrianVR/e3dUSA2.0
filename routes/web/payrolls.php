<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Rutas para el recurso de Nóminas (Payrolls)
    Route::get('payrolls', [PayrollController::class, 'index'])->name('payrolls.index');
    Route::get('payrolls/{payroll}', [PayrollController::class, 'show'])->name('payrolls.show');
    // Rutas tipo API para gestionar incidencias y asistencias desde el frontend
    Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::delete('incidents/{incident}', [IncidentController::class, 'destroy'])->name('incidents.destroy');
    Route::put('attendances/update-day', [AttendanceController::class, 'updateDayAttendances'])->name('attendances.update_day');
    Route::get('payrolls/{payroll}/print', [PayrollController::class, 'print'])->name('payrolls.print'); // Nueva ruta para imprimir
    Route::get('payrolls/{payroll}/employee-details', [PayrollController::class, 'getEmployeePayrollDetails'])->name('payrolls.get-employee-details');
});
