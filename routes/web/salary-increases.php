<?php

use App\Http\Controllers\SalaryIncreaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('salary-increases', [SalaryIncreaseController::class, 'store'])->name('salary-increases.store');
    Route::delete('salary-increases/{salaryIncrease}', [SalaryIncreaseController::class, 'destroy'])->name('salary-increases.destroy');
});
