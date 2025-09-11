<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\DesignOrder;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Este método ahora es solo para la vista principal y los datos que no se cargan dinámicamente.
     * Las estadísticas de producción se han eliminado de aquí.
     */
    public function index()
    {
        // Calendar Widget Data
        $calendarEvents = CalendarEntry::where('start_datetime', '>=', now())
            ->orderBy('start_datetime')
            ->limit(5)
            ->get(['id', 'title', 'start_datetime', 'is_full_day', 'entryable_type']);

        // Warehouse Status Chart
        $warehouseStats = [
            DB::table('products')->where('product_type', 'Materia prima')->count(),
            DB::table('products')->where('product_type', 'Insumo')->count(),
            DB::table('products')->where('product_type', 'Catálogo')->count(),
        ];


        // Required Actions Data
        $requiredActions = [
            'quotes_to_authorize' => Quote::where('status', 'Esperando respuesta')->whereNull('authorized_at')->count(),
            'sales_to_authorize' => Sale::where('status', 'Pendiente')->count(),
            'designs_to_authorize' => DesignOrder::where('status', 'Pendiente')->count(),
            'purchases_to_authorize' => Purchase::where('status', 'Pendiente')->count(),
            'pending_productions' => Production::where('status', 'Pendiente')->count(),
            'unstarted_tasks' => Task::where('status', 'Pendiente')->count(),
            'unstarted_design_orders' => DesignOrder::where('status', 'Autorizada')->whereNull('started_at')->count(),
        ];
        
        // Employee Performance
        $employeePerformance = User::select('id', 'name')
            ->limit(6)
            ->get()
            ->map(function ($user, $index) {
                $points = [603, 590, 520, 520, 440, -20];
                $user->points = $points[$index] ?? 0;
                return $user;
            });

        // 'productionStats' ya no se pasa desde aquí.
        return Inertia::render('Dashboard/Index', [
            'calendarEvents' => $calendarEvents,
            'warehouseStats' => $warehouseStats,
            'requiredActions' => $requiredActions,
            'employeePerformance' => $employeePerformance,
        ]);
    }

    /**
     * método para obtener las estadísticas de producción dinámicamente.
     * Asegúrate de agregar esta ruta en tu archivo `routes/web.php`:
     * Route::get('/dashboard/production-stats', [DashboardController::class, 'getProductionStats'])->name('dashboard.production-stats');
     */
    public function getProductionStats(Request $request)
    {
        // Validar que 'days' sea un entero y uno de los valores permitidos.
        $validated = $request->validate([
            'days' => 'required|integer|in:7,15,30',
        ]);

        $days = $validated['days'];

        $productionStats = Production::where('created_at', '>=', now()->subDays($days))
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json($productionStats);
    }
}
