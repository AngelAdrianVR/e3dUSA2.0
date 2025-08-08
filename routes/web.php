<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;



// Estado de carga de la aplicación
Route::get('/inicio', function () {
    return Inertia::render('Auth/Inicio');
})->name('inicio');

// Rutas de autenticación
Route::get('/', function () {
    return Inertia::render('Auth/Inicio', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});


// ------- Recursos humanos(users routes)  ---------
Route::resource('users', UserController::class)->middleware('auth');
Route::get('users-get-next-attendance', [UserController::class, 'getNextAttendance'])->middleware('auth')->name('users.get-next-attendance');
Route::get('users-get-pause-status', [UserController::class, 'getPauseStatus'])->middleware('auth')->name('users.get-pause-status');
Route::get('users-set-attendance', [UserController::class, 'setAttendance'])->middleware('auth')->name('users.set-attendance');
Route::get('users-set-pause', [UserController::class, 'setPause'])->middleware('auth')->name('users.set-pause');
Route::get('users-get-additional-time-requested-days/{user_id}/{payroll_id}', [UserController::class, 'getRequestedDays'])->middleware('auth')->name('users.get-additional-time-requested-days');
Route::get('users-get-pendent-tasks', [UserController::class, 'getPendentTasks'])->middleware('auth')->name('users.get-pendent-tasks');
Route::put('users-reset-pass/{user}', [UserController::class, 'resetPass'])->middleware('auth')->name('users.reset-pass');
Route::put('users-change-status/{user}', [UserController::class, 'changeStatus'])->middleware('auth')->name('users.change-status');
Route::put('users-update-pausas/{payroll_user}', [UserController::class, 'updatePausas'])->middleware('auth')->name('users.update-pausas');
Route::put('users-update-vacations/{user}', [UserController::class, 'updateVacations'])->middleware('auth')->name('users.update-vacations');
Route::post('users-get-unseen-messages', [UserController::class, 'getUnseenMessages'])->middleware('auth')->name('users.get-unseen-messages');
Route::post('users-get-notifications', [UserController::class, 'getNotifications'])->middleware('auth')->name('users.get-notifications');
Route::post('users-read-notifications', [UserController::class, 'readNotifications'])->middleware('auth')->name('users.read-notifications');
Route::post('users-delete-notifications', [UserController::class, 'deleteNotifications'])->middleware('auth')->name('users.delete-notifications');
Route::get('users-get-all', [UserController::class, 'getAllUsers'])->middleware('auth')->name('users.get-all');
Route::get('users-get-operators', [UserController::class, 'getOperators'])->middleware('auth')->name('users.get-operators');


// ------- Recursos humanos(Bonuses Routes)  ---------
Route::resource('bonuses', BonusController::class)->middleware('auth');
Route::post('bonuses/massive-delete', [BonusController::class, 'massiveDelete'])->name('bonuses.massive-delete');


// ------- Recursos humanos(Discounts Routes)  ---------
Route::resource('discounts', DiscountController::class)->middleware('auth');
Route::post('discounts/massive-delete', [DiscountController::class, 'massiveDelete'])->name('discounts.massive-delete');


// ------- Recursos humanos(Discounts Routes)  ---------
Route::get('/audits', [AuditController::class, 'index'])->name('audits.index');