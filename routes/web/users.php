<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::resource('users', UserController::class)->middleware('auth');
Route::post('users-get-unseen-messages', [UserController::class, 'getUnseenMessages'])->middleware('auth')->name('users.get-unseen-messages');
Route::put('users-change-status/{user}', [UserController::class, 'changeStatus'])->middleware('auth')->name('users.change-status');