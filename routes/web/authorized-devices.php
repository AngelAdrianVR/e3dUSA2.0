<?php

use App\Http\Controllers\AuthorizedDeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('authorized-devices', AuthorizedDeviceController::class)->only(['index', 'store', 'destroy']);
});