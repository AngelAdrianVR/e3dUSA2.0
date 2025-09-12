<?php

use App\Http\Controllers\OvertimeRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('overtime-requests', [OvertimeRequestController::class, 'index'])->name('overtime-requests.index');
    // Para aprobar o rechazar
    Route::put('overtime-requests/{overtimeRequest}', [OvertimeRequestController::class, 'update'])->name('overtime-requests.update');
    Route::post('overtime-requests', [OvertimeRequestController::class, 'store'])->name('overtime-requests.store');
    Route::put('overtime-requests/{overtimeRequest}', [OvertimeRequestController::class, 'update'])->name('overtime-requests.update');
});
