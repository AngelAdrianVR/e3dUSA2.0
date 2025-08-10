<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SparePartController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;



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


// ------- Historial de acciones rutas  ---------
Route::get('/audits', [AuditController::class, 'index'])->middleware('auth')->name('audits.index');


// ------- Roles rutas  ---------
Route::resource('role-permissions', RolePermissionController::class)
->only(['index', 'store', 'update', 'destroy'])
->middleware(['auth', 'verified']);


// ------- Permisos rutas  ---------
Route::resource('permissions', PermissionController::class)
    ->only(['store', 'update', 'destroy'])
    ->middleware(['auth', 'verified']);


// ------- tutorials & manuals routes  -------------
Route::resource('manuals', ManualController::class)->middleware('auth');
Route::put('manuals/increase-views/{manual}', [ManualController::class, 'increaseViews'])->name('manuals.increase-views')->middleware('auth');
Route::post('manuals/update-with-media/{manual}', [ManualController::class, 'updateWithMedia'])->name('manuals.update-with-media')->middleware('auth');


// ------- Machines Routes  ---------
Route::resource('machines', MachineController::class)->middleware('auth');
Route::post('machines/massive-delete', [MachineController::class, 'massiveDelete'])->name('machines.massive-delete');
Route::post('machines/upload-files/{machine}', [MachineController::class, 'uploadFiles'])->name('machines.upload-files');
Route::post('machines/QR-search-machine', [MachineController::class, 'QRSearchMachine'])->name('machines.QR-search-machine');


// ------- Maintenances routes  -------------
Route::resource('maintenances', MaintenanceController::class)->except('create')->middleware('auth');
Route::get('maintenances/create/{selectedMachine}', [MaintenanceController::class, 'create'])->name('maintenances.create')->middleware('auth');
Route::put('maintenances/validate/{maintenance}', [MaintenanceController::class, 'validateWork'])->name('maintenances.validate')->middleware('auth');


// ---------- spare parts routes  ---------------
Route::resource('spare-parts', SparePartController::class)->except('create')->middleware('auth');
Route::get('spare-parts/create/{selectedMachine}', [SparePartController::class, 'create'])->name('spare-parts.create')->middleware('auth');
// Route::post('spare-parts/update-with-media/{spare_part}', [SparePartController::class, 'updateWithMedia'])->name('spare-parts.update-with-media')->middleware('auth');


// eliminacion de archivo desde componente FileView
Route::delete('/media/{media}', function (Media $media) {
    try {
        $media->delete(); // Elimina el archivo y su registro

        return response()->json(['message' => 'Archivo eliminado correctamente.'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al eliminar el archivo.'], 500);
    }
})->name('media.delete-file');