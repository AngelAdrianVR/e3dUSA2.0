<?php

use App\Http\Controllers\VacationLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('vacation-logs', [VacationLogController::class, 'store'])->name('vacation-logs.store');
});