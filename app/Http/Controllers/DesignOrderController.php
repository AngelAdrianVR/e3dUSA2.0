<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Design;
use App\Models\DesignAssignmentLog;
use App\Models\DesignCategory;
use App\Models\DesignOrder;
use App\Models\User;
use App\Notifications\designOrderAuthorizedNotification;
use App\Notifications\DesignOrderFinishedNotification;
use App\Notifications\NewDesignOrderAssignedNotification;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class DesignOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        // Cargamos también las pausas para que la vista Index pueda calcular el tiempo
        $query = DesignOrder::with(['requester:id,name', 'designer:id,name', 'designCategory:id,name', 'pauses']);

        // Contar órdenes sin asignar
        $unassignedOrdersCount = DesignOrder::whereNull('designer_id')->count();

        // Lógica de visibilidad
        if ($request->input('view') === 'all') {
            // Muestra todas las órdenes
        } 
        else if ($request->input('view') === 'unassigned') {
            $query->whereNull('designer_id');
        }
        else if ($user->hasRole(['Diseñador', 'Jefe de Diseño'])) {
            $query->where('designer_id', $user->id);
        }
        else {
            $query->where('requester_id', $user->id);
        }

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $designOrders = $query->latest()->paginate(15)->withQueryString();

        // Mapear el atributo "is_paused" y dar formato al tiempo invertido
        $designOrders->getCollection()->transform(function ($order) {
            $order->is_paused = $order->is_paused;
            
            // Calculamos el tiempo formateado para enviarlo listo a Vue
            if ($order->started_at) {
                $seconds = $order->getActiveTimeInSeconds();
                
                // Si tardó menos de 1 minuto, lo ponemos como tal
                if ($seconds < 60 && !$order->finished_at && !$order->is_paused) {
                    $order->active_time_formatted = 'Menos de 1 min';
                } elseif ($seconds == 0) {
                    $order->active_time_formatted = '0s';
                } else {
                    $order->active_time_formatted = CarbonInterval::seconds($seconds)->cascade()->forHumans(['short' => true]);
                }
            } else {
                $order->active_time_formatted = 'N/A';
            }

            return $order;
        });

        return Inertia::render('Design/Index', [
            'designOrders' => $designOrders,
            'filters' => $request->only(['view', 'status']),
            'unassignedOrdersCount' => $unassignedOrdersCount,
        ]);
    }

    public function create(Request $request)
    {
        $designCategories = DesignCategory::select('id', 'name')->get();
        $designers = User::where('is_active', true)->role(['Diseñador', 'Jefe de Diseño'])->select('id', 'name')->get();
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // --- Handle design modification requests ---
        $originalDesign = null;
        $parentOrder = null;
        if ($request->has('modifies_design')) {
            $originalDesign = Design::find($request->input('modifies_design'));
            // Buscamos la orden original que generó este diseño
            $parentOrder = DesignOrder::where('design_id', $originalDesign->id)->first();
        }

        return Inertia::render('Design/Create', [
            'designCategories' => $designCategories,
            'designers' => $designers,
            'branches' => $branches,
            'originalDesign' => $originalDesign,
            'parentOrder' => $parentOrder, // <-- Se envía la orden padre a la vista
        ]);
    }

    /**
     * Almacena una nueva orden de diseño en la base de datos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // Validación de los datos del formulario con las nuevas reglas
        $validatedData = $request->validate([
            'order_title' => 'required|string|max:255',
            'specifications' => 'required|string',
            'design_category_id' => 'required|exists:design_categories,id',
            'designer_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id', // Se valida que la sucursal exista
            'contact_id' => [
                'nullable',
                'required_if:branch_id,!=,null|exists:contacts,id'
            ], // Requerido solo si hay sucursal
            'due_date' => 'nullable|date',
            'is_hight_priority' => 'required|boolean',
            'media' => 'nullable|array|max:3', // Valida que sea un array y máximo 3 archivos
            'modifies_design_id' => 'nullable|exists:designs,id', // --- Validation for modification
        ]);

        // Añadir el ID del usuario que solicita el diseño.
        $validatedData['requester_id'] = Auth::id();

        // Lógica de estado y asignación basada en la selección del diseñador.
        if (!empty($validatedData['designer_id'])) {
            // Si el vendedor asigna un diseñador, la orden entra 'Pendiente' directamente.
            $validatedData['status'] = 'Pendiente';
            $validatedData['assigned_at'] = now();
        }
        // Si no se asigna diseñador, el estado por defecto 'Pendiente' se usará.

        // Crear la orden de diseño.
        $designOrder = DesignOrder::create($validatedData);

        // --- INICIO: Lógica para Spatie Medialibrary ---
        // Si la solicitud contiene archivos en el campo 'media', los asociamos a la orden.
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $designOrder->addMedia($file)->toMediaCollection('design_order_files');
            }
        }
        // --- FIN: Lógica para Spatie Medialibrary ---

        // Si se asignó un diseñador, crear un registro en el log.
        if ($designOrder->designer_id) {
            DesignAssignmentLog::create([
                'design_order_id' => $designOrder->id,
                'previous_designer_id' => null, // No hay diseñador previo
                'new_designer_id' => $designOrder->designer_id,
                'changed_by_user_id' => Auth::id(), // El solicitante hizo la asignación inicial
                'reason' => 'Asignación inicial al crear la orden.',
                'changed_at' => now(),
            ]);
        }

        // --- INICIO: Lógica de Notificación al Diseñador ---
            $designer = User::find($designOrder->designer_id);
            if ($designer) {
                $designer->notify(new NewDesignOrderAssignedNotification(
                    'Nueva Orden de Diseño',
                    $designOrder->order_title,
                    'design-order',
                    route('design-orders.show', $designOrder->id)
                ));
            }
            // --- FIN: Lógica de Notificación al Diseñador ---
        
        // Redireccionar a la lista de órdenes con un mensaje de éxito.
        return redirect()->route('design-orders.index');
    }

    public function show(DesignOrder $designOrder)
    {
        $designOrders = DesignOrder::select('id', 'order_title')->latest()->take(300)->get();

        $designOrder->load([
            'designAuthorization', 
            'assignmentLogs.newDesigner:id,name', 
            'assignmentLogs.previousDesigner:id,name', 
            'assignmentLogs.changedByUser:id,name', 
            'requester:id,name', 
            'designer:id,name', 
            'branch', 
            'contact', 
            'designCategory:id,name,complexity', 
            'design.media',
            'media',
            'pauses'
        ]);

        $designOrder->is_paused = $designOrder->is_paused;

        $designVersions = collect([]);
        if ($designOrder->design) {
            $originalDesign = $designOrder->design;
            while ($originalDesign->original_design_id) {
                $originalDesign = Design::find($originalDesign->original_design_id);
                if (!$originalDesign) break; 
            }

            if ($originalDesign) {
                $designVersions = Design::with('media')
                    ->where('id', $originalDesign->id)
                    ->orWhere('original_design_id', $originalDesign->id)
                    ->orderBy('created_at')
                    ->get();
            }
        }

        // Enviamos los segundos crudos para que Vue haga el formato dinámico
        $parentOrderDurationSeconds = null;

        if ($designOrder->modifies_design_id) {
            $parentOrder = DesignOrder::with('pauses')->where('design_id', $designOrder->modifies_design_id)->first();
            
            if ($parentOrder && $parentOrder->started_at && $parentOrder->finished_at) {
                $parentOrderDurationSeconds = $parentOrder->getActiveTimeInSeconds();
            }
        }

        return Inertia::render('Design/Show', [
            'designOrder' => $designOrder,
            'designOrders' => $designOrders,
            'designVersions' => $designVersions,
            'parentOrderDurationSeconds' => $parentOrderDurationSeconds,
        ]);
    }

    public function edit(DesignOrder $designOrder)
    {
        // Cargar los archivos multimedia de la orden
        $designOrder->load('media');

        // Se obtienen las categorías de diseño para el selector.
        $designCategories = DesignCategory::select('id', 'name')->get();

        // Se obtienen los usuarios que son diseñadores.
        $designers = User::where('is_active', true)->role(['Diseñador', 'Jefe de Diseño'])->select('id', 'name')->get();

        $branches = Branch::select('id', 'name')->with('contacts')->get();

        return Inertia::render('Design/Edit', [
            'designCategories' => $designCategories,
            'designers' => $designers,
            'designOrder' => $designOrder,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, DesignOrder $designOrder)
    {
        $validatedData = $request->validate([
            'order_title' => 'required|string|max:255',
            'specifications' => 'required|string',
            'design_category_id' => 'required|exists:design_categories,id',
            'designer_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'contact_id' => [
                'nullable',
                'required_if:branch_id,!=,null',
                'exists:contacts,id'
            ],
            'due_date' => 'nullable|date',
            'is_hight_priority' => 'required|boolean',
            'media' => 'nullable|array|max:3',
        ]);

        $previousDesignerId = $designOrder->designer_id;

        // Lógica de estado y asignación basada en la selección del diseñador.
        if (!empty($validatedData['designer_id']) && $validatedData['designer_id'] != $previousDesignerId) {
            // Si el diseñador cambia, se actualiza la fecha de asignación.
            $validatedData['assigned_at'] = now();
        }

        $designOrder->update($validatedData + [
            'status' => 'Pendiente',
            'authorized_at' => null,
            'authorized_user_name' => null,
        ]);

        // --- INICIO: Lógica para Spatie Medialibrary ---
        // Si la solicitud contiene nuevos archivos, reemplazamos los existentes.
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $designOrder->addMedia($file)->toMediaCollection('design_order_files');
            }
        }
        // --- FIN: Lógica para Spatie Medialibrary ---

        // Si se cambió de diseñador, crear un registro en el log.
        if ($designOrder->designer_id != $previousDesignerId) {
            DesignAssignmentLog::create([
                'design_order_id' => $designOrder->id,
                'previous_designer_id' => $previousDesignerId,
                'new_designer_id' => $designOrder->designer_id,
                'changed_by_user_id' => Auth::id(),
                'reason' => 'Cambio de diseñador durante la edición de la solicitud.',
                'changed_at' => now(),
            ]);

            // --- INICIO: Lógica de Notificación al Nuevo Diseñador ---
            $designer = User::find($designOrder->designer_id);
            if ($designer) {
                $designer->notify(new NewDesignOrderAssignedNotification(
                    'Te han asignado una Orden de Diseño',
                    $designOrder->order_title,
                    'design-order',
                    route('design-orders.show', $designOrder->id)
                ));
            }
            // --- FIN: Lógica de Notificación ---
        }
        
        // Redireccionar a la lista de órdenes con un mensaje de éxito.
        return redirect()->route('design-orders.show', $designOrder->id);
    }

    public function destroy(DesignOrder $designOrder)
    {
        $designOrder->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $designOrder = DesignOrder::find($id);
            $designOrder?->delete();
        }
    }

    /**
     * Mark the design order as started by the designer.
     *
     * @param  \App\Models\DesignOrder  $designOrder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function startWork(DesignOrder $designOrder)
    {
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        if ($designOrder->started_at || $designOrder->status !== 'Autorizada') {
            return back()->with('error', 'Esta orden no se puede iniciar.');
        }

        // Actualizamos estado a "En proceso" si lo tienes en tu enum, esto es ideal visualmente
        $designOrder->update([
            'started_at' => now(),
            'status' => 'En proceso'
        ]);

        return back()->with('success', 'El trabajo ha sido iniciado.');
    }

    /**
     * PAUSAR LA ORDEN DE DISEÑO
     */
    public function pauseWork(DesignOrder $designOrder)
    {
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        if (!$designOrder->started_at || $designOrder->finished_at) {
            return back()->with('error', 'Solo las órdenes en progreso pueden pausarse.');
        }

        if ($designOrder->is_paused) {
            return back()->with('error', 'La orden ya se encuentra pausada.');
        }

        $designOrder->pauses()->create([
            'paused_at' => now()
        ]);

        return back()->with('success', 'El temporizador de la orden se ha pausado correctamente.');
    }

    /**
     * REANUDAR LA ORDEN DE DISEÑO
     */
    public function resumeWork(DesignOrder $designOrder)
    {
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        $activePause = $designOrder->pauses()->whereNull('resumed_at')->first();

        if (!$activePause) {
            return back()->with('error', 'La orden no está pausada actualmente.');
        }

        $activePause->update([
            'resumed_at' => now()
        ]);

        return back()->with('success', 'El temporizador de la orden se ha reanudado.');
    }

    /**
     * Mark the design order as finished and create a design asset.
     *
     * @param  \App\Models\DesignOrder  $designOrder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function finishWork(Request $request, DesignOrder $designOrder)
    {
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        
        if (!$designOrder->started_at || $designOrder->finished_at) {
            return back()->with('error', 'Esta orden no se puede finalizar.');
        }

        $request->validate([
            'final_files' => 'required|array|min:1',
            'final_files.*' => 'file|max:20480',
        ]);

        DB::transaction(function () use ($designOrder, $request) {
            
            // 1. Cerrar pausas activas si el diseñador olvidó reanudar antes de terminar
            $activePause = $designOrder->pauses()->whereNull('resumed_at')->first();
            if ($activePause) {
                $activePause->update(['resumed_at' => now()]);
            }

            // 2. Crear el activo de diseño
            $design = Design::create([
                'name' => $designOrder->order_title,
                'description' => $designOrder->specifications,
                'design_category_id' => $designOrder->design_category_id,
                'original_design_id' => $designOrder->modifies_design_id,
            ]);

            foreach ($request->file('final_files') as $file) {
                $design->addMedia($file)->toMediaCollection('completed_files');
            }

            // 3. Actualizar la orden de diseño
            $designOrder->update([
                'finished_at' => now(),
                'status' => 'Terminada',
                'design_id' => $design->id,
            ]);
        });

        $designOrder->load('requester');
        $folio = 'OD-' . str_pad($designOrder->id, 4, "0", STR_PAD_LEFT);
        $designOrder->requester->notify(new DesignOrderFinishedNotification(
            'Orden de Diseño',
            $folio,
            'design_order_finished',
            route('design-orders.show', $designOrder->id)
        ));

        return redirect()->route('design-orders.show', $designOrder->id)->with('success', 'Diseño finalizado y archivado correctamente.');
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $design_orders = DesignOrder::with(['requester:id,name', 'designer:id,name', 'designCategory:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('order_title', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhereHas('requester', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('designer', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $design_orders], 200);
    }

    public function authorizeDesignOrder(DesignOrder $designOrder)
    {
        $designOrder->update([
            'authorized_user_name' => auth()->user()->name,
            'authorized_at' => now(),
            'status' => 'Autorizada',
        ]);

        $designOrder->load('requester');

        // Notificar al creador de la orden si quien autoriza no es el mismo usuario
        if (auth()->id() != $designOrder->requester->id) {
            // Generamos un folio legible para la notificación
            $designOrder_folio = 'OD-' . str_pad($designOrder->id, 4, "0", STR_PAD_LEFT);
            
            // Enviamos la notificación al usuario que creó la venta
            $designOrder->requester->notify(new designOrderAuthorizedNotification(
                'Orden autorizada', // Título de la notificación
                $designOrder_folio, // Folio para mostrar
                route('design-orders.show', $designOrder->id) // URL para redirigir al usuario
            ));
        }

        return response()->json(['message' => 'Orden autorizada', 'item' => $designOrder]);
    }

    public function getDesigners()
    {
        $designers = User::with([
            // Aplicar restricción a la relación 'assignedDesignOrders'
            'assignedDesignOrders' => function ($query) {
                // Seleccionar solo las órdenes que NO tengan estos estatus
                $query->whereNotIn('status', ['Terminada', 'Cancelada']);
            },
            // Cargar relaciones anidadas de las órdenes ya filtradas
            'assignedDesignOrders.branch:id,name',
            'assignedDesignOrders.designCategory:id,name,complexity',
        ])
        ->where('is_active', true) // O el criterio que uses para identificar diseñadores
        ->role(['Diseñador', 'Jefe de Diseño'])
        ->select('id', 'name')
        ->get();

        return response()->json($designers);
    }

    public function assignDesigner(Request $request, DesignOrder $designOrder)
    {
        $validatedData = $request->validate([
            'designer_id' => 'required|exists:users,id',
        ]);
        
        // Guardar el diseñador anterior para el log
        $previousDesignerId = $designOrder->designer_id;

        // Actualizar la orden de diseño
        $designOrder->update([
            'designer_id' => $validatedData['designer_id'],
            'assigned_at' => now(),
            'status' => 'Pendiente', // Se cambia a pendiente para que siga el flujo
        ]);

        // Crear un registro en el log de asignaciones
        DesignAssignmentLog::create([
            'design_order_id' => $designOrder->id,
            'previous_designer_id' => $previousDesignerId,
            'new_designer_id' => $validatedData['designer_id'],
            'changed_by_user_id' => Auth::id(),
            'reason' => 'Asignación desde panel de órdenes.',
            'changed_at' => now(),
        ]);

        // Notificar al nuevo diseñador
        $designer = User::find($validatedData['designer_id']);
        if ($designer) {
            $designer->notify(new NewDesignOrderAssignedNotification(
                'Nueva Orden de Diseño Asignada',
                $designOrder->order_title,
                'design-order',
                route('design-orders.show', $designOrder->id)
            ));
        }
        
        return back();
    }

    public function checkSimilar(Request $request)
    {
        $request->validate([
            'order_title' => 'nullable|string|max:255',
            'design_category_id' => 'required|integer|exists:design_categories,id',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $title = $request->input('order_title');
        $categoryId = $request->input('design_category_id');
        $branchId = $request->input('branch_id');

        // Si no hay título, no podemos comparar, retornamos un arreglo vacío.
        if (!$title) {
            return response()->json([]);
        }

        // Dividimos el título en palabras clave, ignorando las muy cortas.
        $words = array_filter(explode(' ', Str::lower($title)), function ($word) {
            return strlen($word) > 2;
        });

        if (empty($words)) {
            return response()->json([]);
        }

        $query = DesignOrder::query();

        // Condición: La sucursal debe coincidir (si se proporciona)
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Condición: La categoría de diseño debe coincidir
        $query->where('design_category_id', $categoryId);

        // Condición: El título debe contener al menos una de las palabras clave
        $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $searchTerm = '%' . str_replace(['%', '_'], ['\%', '\_'], $word) . '%';
                $q->orWhere('order_title', 'LIKE', $searchTerm);
            }
        });

        // Cargamos la relación con el solicitante para mostrar su nombre
        // y seleccionamos solo los campos necesarios.
        $similarOrders = $query->with('requester:id,name', 'branch:id,name', 'designer:id,name')
            ->select('id', 'order_title', 'status', 'created_at', 'requester_id', 'branch_id', 'designer_id')
            ->latest() // Mostramos las más recientes primero
            ->take(10)   // Limitamos la cantidad de resultados
            ->get();

        return response()->json($similarOrders);
    }

    public function getDesignersActivityReport(Request $request)
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
            'designers' => 'required|array',
            'designers.*' => 'exists:users,id',
        ]);

        $month = Carbon::createFromFormat('Y-m', $request->month);
        $designersIds = $request->designers;

        $designers = User::whereIn('id', $designersIds)->get();
        $reportData = [];

        setlocale(LC_TIME, 'es_ES.UTF-8');
        $monthName = ucfirst($month->translatedFormat('F'));
        $year = $month->year;

        foreach ($designers as $designer) {
            // Se le carga la relación 'pauses' para calcular el tiempo real
            $orders = DesignOrder::with('pauses')
                ->where('designer_id', $designer->id)
                ->where('status', 'Terminada')
                ->whereYear('finished_at', $year)
                ->whereMonth('finished_at', $month->month)
                ->get();

            $totalDurationInSeconds = 0;
            $processedOrders = [];
            $canCalculateAnyDuration = false;

            foreach ($orders as $order) {
                $duration = 'N/A';
                $pausesData = [];

                if ($order->started_at && $order->finished_at) {
                    $canCalculateAnyDuration = true;
                    
                    $diffInSeconds = $order->getActiveTimeInSeconds();
                    $totalDurationInSeconds += $diffInSeconds;

                    $duration = CarbonInterval::seconds($diffInSeconds)->cascade()->forHumans(['short' => true]);
                    if ($diffInSeconds === 0) {
                        $duration = '0s';
                    }

                    // Procesar el historial de pausas para la vista
                    foreach ($order->pauses as $pause) {
                        $pStart = $pause->paused_at;
                        $pEnd = $pause->resumed_at;
                        $pDuration = 'En curso';

                        if ($pEnd) {
                            $pDuration = CarbonInterval::seconds($pEnd->diffInSeconds($pStart))->cascade()->forHumans(['short' => true]);
                        }

                        $pausesData[] = [
                            'paused_at' => $pStart?->format('d/m/Y H:i'),
                            'resumed_at' => $pEnd?->format('d/m/Y H:i') ?? 'N/A',
                            'duration' => $pDuration,
                        ];
                    }
                }

                $processedOrders[] = [
                    'id' => $order->id,
                    'order_title' => $order->order_title,
                    'started_at' => $order->started_at?->format('d/m/Y H:i'),
                    'finished_at' => $order->finished_at?->format('d/m/Y H:i'),
                    'duration' => $duration,
                    'pauses' => $pausesData, // Enviamos el historial de pausas
                ];
            }
            
            $totalDurationFormatted = 'N/A';
            if ($canCalculateAnyDuration) {
                 $totalDurationFormatted = CarbonInterval::seconds($totalDurationInSeconds)->cascade()->forHumans();
                 if (empty($totalDurationFormatted)) {
                    $totalDurationFormatted = '0 minutos';
                }
            }

            $reportData[] = [
                'designer_name' => $designer->name,
                'orders' => $processedOrders,
                'total_orders' => count($processedOrders),
                'total_duration' => $totalDurationFormatted,
            ];
        }

        return Inertia::render('Design/DesignersActivityReport', [
            'reportData' => $reportData,
            'monthName' => $monthName,
            'year' => $year,
        ]);
    }

}
