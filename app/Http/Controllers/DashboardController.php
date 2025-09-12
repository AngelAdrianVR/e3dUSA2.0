<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\Contact;
use App\Models\DesignOrder;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionTask;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SampleTracking;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $authUserId = Auth::id();
        $authUser = Auth::user();
        
        // Calendar Widget Data
        $calendarEvents = CalendarEntry::where('start_datetime', '>=', now())
            ->orderBy('start_datetime')
            ->limit(5)
            ->get(['id', 'title', 'start_datetime', 'is_full_day', 'entryable_type']);

        // Warehouse Status Chart
        // Se calcula la cantidad de productos con stock bajo.
        // Esto asume que tu modelo 'Product' tiene una columna 'min_quantity' y una relación 'storage'.
        $lowStockCount = Product::whereNotNull('min_quantity')
            ->whereHas('storages', function ($query) {
                // Compara la cantidad en storage con la cantidad mínima en products
                $query->whereRaw('storages.quantity <= products.min_quantity');
            })->count();

        // Se reestructura el array para enviar un objeto a la vista.
        $warehouseStats = [
            'counts' => [
                DB::table('products')->where('product_type', 'Materia prima')->count(),
                DB::table('products')->where('product_type', 'Insumo')->count(),
                DB::table('products')->where('product_type', 'Catálogo')->count(),
            ],
            'lowStockCount' => $lowStockCount,
        ];


        // Required Actions Data
        $requiredActions = [
            'quotes_to_authorize' => Quote::whereNull('authorized_at')->count(),
            'sales_to_authorize' => Sale::whereNull('authorized_at')->count(),
            'designs_to_authorize' => DesignOrder::whereNull('authorized_at')->count(),
            'purchases_to_authorize' => Purchase::whereNull('authorized_at')->count(),
            'sample_trackings_to_authorize' => SampleTracking::whereNull('authorized_at')->count(),
            'sales_without_ov' => Sale::with('productions')->whereDoesntHave('productions')->whereNotNull('authorized_at')->count(),
            'pending_productions' => Production::where('status', 'Pendiente')->count(),
            'unstarted_tasks' => ProductionTask::where('status', 'Pendiente')->count(),
            'unstarted_design_orders' => DesignOrder::where('status', 'Autorizada')->whereNull('started_at')->count(),
        ];
        
        // Employee Performance
        // Define the start and end of the current week (Monday to Saturday)
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->startOfWeek()->addDays(5)->endOfDay();

        // 1. Calculate points from sales for the current week
        $salesPoints = Sale::whereNotNull('authorized_at')
            ->whereBetween('authorized_at', [$startOfWeek, $endOfWeek])
            ->select('user_id', DB::raw('SUM(CASE WHEN currency = "USD" THEN total_amount * 20 ELSE total_amount END) as points'))
            ->groupBy('user_id')
            ->pluck('points', 'user_id');

        // 2. Calculate points from production tasks for the current week
        $productionPoints = ProductionTask::where('status', 'Terminada')
            ->whereNotNull('operator_id')
            ->whereBetween('finished_at', [$startOfWeek, $endOfWeek])
            ->select('operator_id', DB::raw('SUM(estimated_time_minutes) as points'))
            ->groupBy('operator_id')
            ->pluck('points', 'operator_id');

        // 3. Combine points for each user, sort, and get the top 8 performers
        $employeePerformance = User::get()->map(function ($user) use ($salesPoints, $productionPoints) {
            $user->points = round(
                ($salesPoints[$user->id] ?? 0) + ($productionPoints[$user->id] ?? 0)
            );
            return $user;
        })->filter(function ($user) {
            return $user->points != 0; // Filter out users with 0 points
        })->sortByDesc('points')->values()->take(8); 

        // 1. Upcoming Birthdays (next 30 days)
        $upcomingBirthdays = Contact::with(['branch', 'details'])
            ->whereNotNull('birthdate')
            ->whereRaw('DAYOFYEAR(birthdate) BETWEEN DAYOFYEAR(CURDATE()) AND DAYOFYEAR(CURDATE() + INTERVAL 30 DAY)')
            ->orderByRaw('DAYOFYEAR(birthdate)')
            ->get()
            ->map(function ($contact) {
                // Find primary email or first email
                $email = $contact->details->firstWhere('is_primary', true)->value ?? $contact->details->firstWhere('type', 'email')->value ?? null;
                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'company_name' => $contact->branch->name ?? 'N/A',
                    'birthdate' => $contact->birthdate,
                    'email' => $email,
                ];
            });

        // 2. My Sales Orders (not in production yet)
        $mySalesOrders = Sale::where('user_id', $authUserId)
            ->whereIn('status', ['Pendiente', 'Autorizada', 'En Proceso', 'En Producción', 'Preparando Envío'])
            ->latest()
            ->limit(10)
            ->get(['id', 'created_at', 'is_high_priority', 'status'])
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'status' => $sale->status,
                    'folio' => 'OV-' . $sale->id, // Folio generado dinámicamente
                    'created_at' => $sale->created_at,
                    'requires_follow_up' => $sale->is_high_priority, // Usando el campo correcto
                ];
            });

        // 3. My Pending Tasks
        $myPendingTasks = ProductionTask::with('production.sale', 'production.saleProduct')
            ->where('operator_id', $authUserId)
            ->whereIn('status', ['Pendiente', 'En proceso', 'Pausada', 'Sin material'])
            ->orderBy('created_at')
            ->limit(7)
            ->get()
            ->map(function($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'created_at' => $task->created_at->isoFormat('D MMMM YYYY'),
                    'production_folio' => 'OV-' . $task->production->sale->id ?? 'N/A',
                    'pieces_ordered' => $task->production->saleProduct->quantity ?? 0, // Cantidad total del item de la venta
                    'pieces_available' => $task->production->saleProduct->quantity - $task->production->saleProduct->quantity_to_produce,
                    'pieces_to_produce' => $task->production->saleProduct->quantity_to_produce ?? 0, // Cantidad a producir del item de la venta
                ];
            });

        return Inertia::render('Dashboard/Index', [
            'calendarEvents' => $calendarEvents,
            'warehouseStats' => $warehouseStats,
            'requiredActions' => $requiredActions,
            'employeePerformance' => $employeePerformance,
            'upcomingBirthdays' => $upcomingBirthdays,
            'mySalesOrders' => $mySalesOrders,
            'myPendingTasks' => $myPendingTasks,
            'authUserName' => $authUser?->name,
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
