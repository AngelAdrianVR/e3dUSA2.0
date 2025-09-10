<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    /**
     * Define las rutas para la gestión de registros de asistencia.
     * Estas rutas son utilizadas por la API interna del frontend (axios)
     * para manejar la edición de entradas, salidas y descansos en el modal.
     */

    // Obtiene los registros de asistencia de un empleado para un día específico.
    Route::get('attendances/{employee}/{date}', [AttendanceController::class, 'getForDay'])->name('attendances.get-for-day');

    // Actualiza todos los registros de asistencia de un día para un empleado.
    Route::put('attendances/{employee}/{date}', [AttendanceController::class, 'update'])->name('attendances.update');

    // Alterna el estado de "ignorar retardo" para un registro de entrada
    Route::post('attendances/toggle-ignore-late/{attendance}', [AttendanceController::class, 'toggleIgnoreLate'])->name('attendances.toggle-ignore-late');
});
