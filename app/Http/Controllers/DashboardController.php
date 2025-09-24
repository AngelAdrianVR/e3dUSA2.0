<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CalendarEntry;
use App\Models\Contact;
use App\Models\DesignOrder;
use App\Models\EmployeeDetail;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\OvertimeRequest;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionTask;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SampleTracking;
use App\Models\User;
use Carbon\Carbon;
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
        
        $calendarEvents = CalendarEntry::where('start_datetime', '>=', now())
            ->where(function ($query) use ($authUserId) {
                $query->where('user_id', $authUserId)
                    ->orWhereHasMorph(
                        'entryable',
                        [Event::class],
                        function ($q) use ($authUserId) {
                            $q->whereHas('participants', function ($subQ) use ($authUserId) {
                                $subQ->where('users.id', $authUserId);
                            });
                        }
                    );
            })
            ->with([
                // Eager load para optimizar consultas a la base de datos
                'entryable' => function ($morphTo) use ($authUserId) {
                    $morphTo->morphWith([
                        Event::class => [
                            // De la relación 'participants', solo trae al usuario actual
                            'participants' => function ($query) use ($authUserId) {
                                $query->where('users.id', $authUserId);
                            }
                        ]
                    ]);
                }
            ])
            ->orderBy('start_datetime')
            ->limit(5)
            ->get()
            // Transforma la colección para añadir el estado del participante
            ->map(function ($entry) {
                // Busca si se cargó la información del participante
                $participant = $entry->entryable?->participants?->first();

                // Si existe, añade el estado. Si no, déjalo como null.
                $entry->participant_status = $participant ? $participant->pivot->status : null;
                
                // Opcional: Remueve la relación para no cargar datos innecesarios en la vista
                unset($entry->entryable);

                return $entry;
            });

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
        if ( $authUser->hasRole('Super Administrador') ) {
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
        }
        
        // Employee Performance
        // --- Performance Data by Role ---
        $productionPerformance = $this->getPerformanceData('Auxiliar de producción');
        $salesPerformance = $this->getPerformanceData('Vendedor');
        $designPerformance = $this->getPerformanceData('Diseñador');

        // 1. Upcoming Birthdays (next 30 days)
        $upcomingBirthdays = Contact::with(['contactable', 'details']) // 1. Cambiado 'branch' por 'contactable'
            ->whereNotNull('birthdate')
            ->whereRaw('DAYOFYEAR(birthdate) BETWEEN DAYOFYEAR(CURDATE()) AND DAYOFYEAR(CURDATE() + INTERVAL 30 DAY)')
            ->orderByRaw('DAYOFYEAR(birthdate)')
            ->get()
            ->map(function ($contact) {
                // La lógica para el email no cambia
                $email = $contact->details->firstWhere('is_primary', true)->value ?? $contact->details->firstWhere('type', 'email')->value ?? null;

                // 2. Lógica para obtener el nombre de la compañía de forma segura
                $company_name = 'N/A'; // Valor por defecto
                
                // Verificamos que el "contactable" existe y es una instancia de Branch
                if ($contact->contactable && $contact->contactable instanceof \App\Models\Branch) {
                    $company_name = $contact->contactable->name;
                } else if ($contact->contactable && $contact->contactable instanceof \App\Models\Supplier) {
                    $company_name = $contact->contactable->name;
                }

                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'company_name' => $company_name,
                    'birthdate' => $contact->birthdate,
                    'email' => $email,
                ];
        });

        // --- INICIA NUEVA CONSULTA: Mis Facturas Pendientes de Pago ---
        $myPendingInvoices = collect();
        if ($authUser->hasRole(['Vendedor', 'Super Administrador', 'Asistente de director'])) {
            $myPendingInvoices = Invoice::where('user_id', $authUserId)
                ->whereIn('status', ['Pendiente', 'Parcialmente pagada'])
                ->with('payments') // Eager load payments para optimizar cálculo de pendiente
                ->latest('due_date')
                ->limit(10)
                ->get()
                ->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'folio' => $invoice->folio,
                        'status' => $invoice->status,
                        'pending_amount' => $invoice->pending_amount, // Usa el accesor del modelo
                        'total_amount' => $invoice->amount,
                        'currency' => $invoice->currency,
                        'due_date' => $invoice->due_date->isoFormat('D MMM, YYYY'),
                    ];
                });
        }

        // 2. My Sales Orders
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
        if ( $authUser->hasRole('Auxiliar de producción') ) {
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
        }
            
        // panel de novedades
        $currentMonth = now()->month;

        // 1. Birthdays for the current month, sorted by day
        $birthdays = EmployeeDetail::whereNotNull('birthdate')
            ->whereMonth('birthdate', $currentMonth)
            ->whereHas('user', function ($query) {
                $query->where('is_active', true);
            })
            ->with('user')
            ->get()
            ->sortBy(fn($detail) => $detail->birthdate->format('d'));

        // 2. Anniversaries for the current month, sorted by day
        $anniversaries = EmployeeDetail::whereNotNull('join_date')
            ->whereMonth('join_date', $currentMonth)
            ->whereYear('join_date', '<', now()->year) // Excluye contrataciones de este año
            ->whereHas('user', function ($query) {
                $query->where('is_active', true);
            })
            ->with('user')
            ->get()
            ->sortBy(fn($detail) => $detail->join_date->format('d'));

        // 3. New hires in the current month and year
        $newHires = EmployeeDetail::whereMonth('join_date', $currentMonth)
            ->whereYear('join_date', now()->year)
            ->with('user')
            ->latest('join_date')
            ->get();

        // 4. Combine and format news items
        $news = collect([]);
        foreach ($birthdays as $employee) {
            $news->push([
                'user_name' => $employee->user->name,
                'department' => $employee->department,
                'secondary_string' => '¡Feliz Cumpleaños!',
                'date_string' => $employee->birthdate->isoFormat('D MMMM'),
                'sort_date' => $employee->birthdate->day,
                'type' => 'birthday',
            ]);
        }
        foreach ($anniversaries as $employee) {
            // FIX: Cast the result of diffInYears to an integer to remove decimals.
            $years = (int) $employee->join_date->diffInYears(now());
            if ($years > 0) {
                $news->push([
                    'user_name' => $employee->user->name,
                    'department' => $employee->department,
                    'secondary_string' => "Aniversario ($years años)",
                    'date_string' => $employee->join_date->isoFormat('D MMMM'),
                    'sort_date' => $employee->join_date->day,
                    'type' => 'anniversary',
                ]);
            }
        }
        foreach ($newHires as $employee) {
            $news->push([
                'user_name' => $employee->user->name,
                'department' => $employee->department,
                'secondary_string' => 'Nuevo Ingreso',
                'date_string' => $employee->join_date->isoFormat('D MMMM'),
                'sort_date' => $employee->join_date->day,
                'type' => 'new_hire',
            ]);
        }
        $news = $news->sortBy('sort_date')->values();

        // ------------- Órdenes de Venta Autorizadas y Pendientes -------------
        $availableSales = Sale::with(['contact', 'user', 'saleProducts.product.storages', 'saleProducts.product.media']) // Carga anticipada de relaciones
            ->whereIn('status', ['Autorizada', 'Pendiente'])
            ->latest('authorized_at')
            ->limit(8)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'folio' => 'OV-' . str_pad($sale->id, 4, '0', STR_PAD_LEFT),
                    'contact_name' => $sale->contact->name,
                    'user_name' => $sale->user->name,
                    'total' => '$' . number_format($sale->total_amount, 2) . ' ' . $sale->currency,
                    'date' => Carbon::parse($sale->authorized_at)->isoFormat('D MMM, YYYY'),
                    'status' => $sale->status,
                    'products' => $sale->saleProducts->map(function ($saleProduct) {
                        // Asegurarse de que el producto existe para evitar errores
                        if (!$saleProduct->product) {
                            return null;
                        }
                        return [
                            'id' => $saleProduct->id,
                            'name' => $saleProduct->product->name,
                            'media' => $saleProduct->product->media,
                            'quantity_to_produce' => $saleProduct->quantity_to_produce,
                            'stock_available' => $saleProduct->product->storages->sum('quantity') ?? 0,
                        ];
                    })->filter(), // Elimina cualquier producto nulo si la relación falla
                ];
            });

            // ------------- NUEVA CONSULTA: Solicitudes de tiempo extra pendientes del usuario -------------
            $pendingOvertimeRequests = collect();
            // Asegurarse de que el usuario autenticado es un empleado con detalles
            if ($authUser->hasRole('Auxiliar de producción')) {
                // cargar detalles de personal
                $authUser->load('employeeDetail');

                $pendingOvertimeRequests = OvertimeRequest::where('employee_detail_id', $authUser->employeeDetail->id)
                    ->where('date', '>=', now()->startOfDay()) // Solicitudes de hoy en adelante
                    ->orderBy('date')
                    ->get(['id', 'date', 'requested_minutes', 'status'])
                    ->map(function($request) {
                        return [
                            'id' => $request->id,
                            'date' => $request->date->isoFormat('D MMMM, YYYY'), // Formatear fecha
                            'requested_minutes' => $request->requested_minutes,
                            'status' => $request->status,
                        ];
                    });
            }

        return Inertia::render('Dashboard/Index', [
            'calendarEvents' => $calendarEvents,
            'warehouseStats' => $warehouseStats,
            'requiredActions' => $requiredActions ?? null,
            'upcomingBirthdays' => $upcomingBirthdays,
            'myPendingInvoices' => $myPendingInvoices,
            'mySalesOrders' => $mySalesOrders,
            'myPendingTasks' => $myPendingTasks ?? null,
            'authUserName' => $authUser?->name,
            'news' => $news,
            'productionPerformance' => $productionPerformance,
            'salesPerformance' => $salesPerformance,
            'designPerformance' => $designPerformance,
            'availableSales' => $availableSales,
            'pendingOvertimeRequests' => $pendingOvertimeRequests,
        ]);

    }

    /**
     * Calculates performance points for users based on their role.
     */
    private function getPerformanceData($roleName)
    {
        Carbon::setLocale('es');
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $users = User::role($roleName)->whereHas('employeeDetail')->with('employeeDetail')->get();

        if ($roleName === 'Vendedor') {
            $weeklySales = Sale::whereNotNull('authorized_at')
                ->whereBetween('authorized_at', [$startOfWeek, $endOfWeek])
                ->select('id', 'user_id', 'authorized_at', 'total_amount', 'currency')
                ->get();
            $salesByUser = $weeklySales->groupBy('user_id');

            return $users->map(function ($user) use ($salesByUser, $startOfWeek, $endOfWeek) {
                $userSales = $salesByUser->get($user->id, collect());
                if ($userSales->isEmpty()) return null;

                $totalPoints = $userSales->sum(fn($sale) => $sale->currency === 'USD' ? $sale->total_amount * 20 : $sale->total_amount);
                $salesByDay = $userSales->groupBy(fn($sale) => Carbon::parse($sale->authorized_at)->format('Y-m-d'));
                $dailyDetails = [];

                for ($date = $startOfWeek->copy(); $date->lte($endOfWeek); $date->addDay()) {
                    if ($date->isSunday()) continue;
                    $dayKey = $date->format('Y-m-d');
                    $daySales = $salesByDay->get($dayKey, collect());
                    $dayTotal = $daySales->sum(fn($sale) => $sale->currency === 'USD' ? $sale->total_amount * 20 : $sale->total_amount);
                    $dailyDetails[] = ['date' => $date->isoFormat('ddd, D MMM'), 'amount' => round($dayTotal)];
                }

                $formattedSales = $userSales->map(fn($sale) => [
                    'folio' => 'OV-' . $sale->id,
                    'date' => Carbon::parse($sale->authorized_at)->isoFormat('ddd, D MMM HH:mm'),
                    'amount' => '$' . number_format($sale->total_amount, 2) . ' ' . $sale->currency,
                ])->all();

                return ['id' => $user->id, 'name' => $user->name, 'points' => round($totalPoints), 'details' => $dailyDetails, 'weekly_tasks' => $formattedSales, 'type' => 'sales'];
            })->filter()->sortByDesc('points')->values();
        }

        if ($roleName === 'Diseñador') {
            $weeklyDesigns = DesignOrder::where('status', 'Terminada')->whereNotNull('finished_at')->whereBetween('finished_at', [$startOfWeek, $endOfWeek])->select('id', 'designer_id', 'order_title', 'finished_at')->get();
            $designsByUser = $weeklyDesigns->groupBy('designer_id');

            return $users->map(function ($user) use ($designsByUser, $startOfWeek, $endOfWeek) {
                $userDesigns = $designsByUser->get($user->id, collect());
                if ($userDesigns->isEmpty()) return null;

                $totalPoints = $userDesigns->count() * 50;
                $designsByDay = $userDesigns->groupBy(fn($design) => Carbon::parse($design->finished_at)->format('Y-m-d'));
                $dailyDetails = [];

                for ($date = $startOfWeek->copy(); $date->lte($endOfWeek); $date->addDay()) {
                    if ($date->isSunday()) continue;
                    $dayKey = $date->format('Y-m-d');
                    $dailyDetails[] = ['date' => $date->isoFormat('ddd, D MMM'), 'orders' => $designsByDay->get($dayKey, collect())->count()];
                }

                $formattedDesigns = $userDesigns->map(fn($design) => [
                    'folio' => 'OD-' . $design->id,
                    'title' => $design->order_title,
                    'date' => Carbon::parse($design->finished_at)->isoFormat('ddd, D MMM HH:mm'),
                ])->all();

                return ['id' => $user->id, 'name' => $user->name, 'points' => $totalPoints, 'details' => $dailyDetails, 'weekly_tasks' => $formattedDesigns, 'type' => 'design'];
            })->filter()->sortByDesc('points')->values();
        }

        if ($roleName === 'Auxiliar de producción') {
            return $users->map(function ($user) use ($startOfWeek, $endOfWeek) {
                $dailyDetails = [];
                for ($date = $startOfWeek->copy(); $date->lte($endOfWeek); $date->addDay()) {
                    if ($date->isSunday()) continue;

                    $dayPoints = ['date' => $date->isoFormat('ddd, D MMM'), 'punctuality' => 0, 'time' => 0, 'scrap' => 0, 'day_finished' => 0, 'extra_time' => 0, 'quality_supervision' => 0];
                    
                    $attendance = Attendance::where('employee_detail_id', $user->employeeDetail->id)->whereDate('timestamp', $date)->where('type', 'check_in')->first();
                    if ($attendance) $dayPoints['punctuality'] = $attendance->late_minutes > 0 ? -10 : 10;

                    $dayPoints['time'] = (int)ProductionTask::where('operator_id', $user->id)->where('status', 'Terminada')->whereDate('finished_at', $date)->sum(DB::raw('TIMESTAMPDIFF(MINUTE, started_at, finished_at)'));

                    $tasksOnDay = ProductionTask::where('operator_id', $user->id)->where('status', 'Terminada')->whereDate('finished_at', $date)->with('production')->get();
                    $totalScrap = $tasksOnDay->sum('production.scrap');
                    if ($totalScrap > 0) $dayPoints['scrap'] = -$totalScrap;
                    elseif ($tasksOnDay->isNotEmpty()) $dayPoints['scrap'] = 1;

                    if ($user->employeeDetail && $user->employeeDetail->hours_per_week > 0 && count($user->employeeDetail->work_days) > 0) {
                        $requiredHours = $user->employeeDetail->hours_per_week / count($user->employeeDetail->work_days);
                        $check_in = Attendance::where('employee_detail_id', $user->employeeDetail->id)->whereDate('timestamp', $date)->where('type', 'check_in')->first();
                        $check_out = Attendance::where('employee_detail_id', $user->employeeDetail->id)->whereDate('timestamp', $date)->where('type', 'check_out')->latest('timestamp')->first();
                        if($check_in && $check_out) {
                            $dayPoints['day_finished'] = $check_out->timestamp->diffInHours($check_in->timestamp) < $requiredHours ? -50 : '✓';
                        }
                    }
                    $dailyDetails[] = $dayPoints;
                }
                
                if (OvertimeRequest::where('employee_detail_id', $user->employeeDetail->id)->where('status', 'Aprobado')->whereBetween('date', [$startOfWeek, $endOfWeek])->exists()) {
                     if (isset($dailyDetails[0])) $dailyDetails[0]['extra_time'] = 100;
                }
                
                $totalPoints = collect($dailyDetails)->sum(fn($day) => $day['punctuality'] + $day['time'] + $day['scrap'] + ($day['day_finished'] === -50 ? -50 : 0) + $day['extra_time'] + $day['quality_supervision']);

                $weeklyTasks = ProductionTask::with(['production.sale'])->where('operator_id', $user->id)->where('status', 'Terminada')->whereBetween('finished_at', [$startOfWeek, $endOfWeek])->get();
                $formattedTasks = $weeklyTasks->map(function($task) {
                    $startTime = Carbon::parse($task->started_at);
                    $finishTime = Carbon::parse($task->finished_at);
                    return [
                        'folio' => 'OP-' . ($task->production->id ?? 'N/A'),
                        'sale_folio' => 'OV-' . ($task->production->sale->id ?? 'N/A'),
                        'name' => $task->name,
                        'started_at' => $startTime->isoFormat('ddd, D MMM HH:mm'),
                        'finished_at' => $finishTime->isoFormat('ddd, D MMM HH:mm'),
                        'duration_minutes' => $finishTime->diffInMinutes($startTime),
                    ];
                })->all();

                return ['id' => $user->id, 'name' => $user->name, 'points' => round($totalPoints), 'details' => $dailyDetails, 'weekly_tasks' => $formattedTasks, 'type' => 'production'];
            })->sortByDesc('points')->values();
        }

        return collect([]);
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
