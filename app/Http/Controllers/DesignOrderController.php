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
        $query = DesignOrder::with(['requester:id,name', 'designer:id,name', 'designCategory:id,name']);

        // Contar órdenes sin asignar para el indicador numérico.
        // Este conteo se realiza antes de aplicar los filtros de vista.
        $unassignedOrdersCount = DesignOrder::whereNull('designer_id')->count();

        // Lógica de visibilidad
        if ($request->input('view') === 'all') {
            // Muestra todas las órdenes, sin filtro de usuario.
        } 
        else if ($request->input('view') === 'unassigned') {
            // Nuevo: Filtra para mostrar solo órdenes sin diseñador asignado.
            $query->whereNull('designer_id');
        }
        // Si el usuario es un diseñador (asumiendo que tiene un rol o permiso específico)
        else if ($user->isDesigner()) { // Deberás crear este método en tu modelo User
            $query->where('designer_id', $user->id);
        }
        // Para cualquier otro caso (vista "Mías" por defecto para solicitantes o gerentes)
        else {
            $query->where('requester_id', $user->id);
        }

        $designOrders = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Design/Index', [
            'designOrders' => $designOrders,
            'filters' => $request->only(['view']),
            'unassignedOrdersCount' => $unassignedOrdersCount, // Pasamos el contador a la vista
        ]);
    }

    public function create(Request $request)
    {
        $designCategories = DesignCategory::select('id', 'name')->get();
        $designers = User::where('is_active', true)->select('id', 'name')->get();
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Se obtienen los usuarios que son diseñadores.
        // Asumiendo que los diseñadores tienen un campo 'designer_level' no nulo como indica el flujo.
        // $designers = User::whereNotNull('designer_level')
        //                  ->where('status', 'active') // O cualquier otro criterio para usuarios activos
        //                  ->select('id', 'name')
        //

        // --- Handle design modification requests ---
        $originalDesign = null;
        if ($request->has('modifies_design')) {
            $originalDesign = Design::find($request->input('modifies_design'));
        }

        return Inertia::render('Design/Create', [
            'designCategories' => $designCategories,
            'designers' => $designers,
            'branches' => $branches,
            'originalDesign' => $originalDesign, // Pass original design to the view
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
            'media.*' => 'file|max:10240', // Límite de 10MB por archivo (10240 KB), puedes ajustarlo
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
                'reason' => 'Asignación inicial al crear la solicitud.',
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
        $designOrders = DesignOrder::select('id', 'order_title')->get();

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
        ]);

        // --- Logic to get all design versions ---
        $designVersions = collect([]);
        if ($designOrder->design) {
            $originalDesign = $designOrder->design;
            // Traverse up to find the absolute original design
            while ($originalDesign->original_design_id) {
                $originalDesign = Design::find($originalDesign->original_design_id);
                if (!$originalDesign) break; // Break if something is wrong with the chain
            }

            if ($originalDesign) {
                // Get the original and all designs that reference it
                $designVersions = Design::with('media')
                    ->where('id', $originalDesign->id)
                    ->orWhere('original_design_id', $originalDesign->id)
                    ->orderBy('created_at')
                    ->get();
            }
        }

        return Inertia::render('Design/Show', [
            'designOrder' => $designOrder,
            'designOrders' => $designOrders,
            'designVersions' => $designVersions, // Pass versions to the view
        ]);
    }

    public function edit(DesignOrder $designOrder)
    {
        // Cargar los archivos multimedia de la orden
        $designOrder->load('media');

        // Se obtienen las categorías de diseño para el selector.
        $designCategories = DesignCategory::select('id', 'name')->get();

        // Se obtienen los usuarios que son diseñadores.
        // Asumiendo que los diseñadores tienen un campo 'designer_level' no nulo como indica el flujo.
        // $designers = User::whereNotNull('designer_level')
        //                  ->where('status', 'active') // O cualquier otro criterio para usuarios activos
        //                  ->select('id', 'name')
        //                  ->get();

        $designers = User::where('is_active', true) // O cualquier otro criterio para usuarios activos
                         ->select('id', 'name')
                         ->get();

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
            'media.*' => 'file|max:10240' // 10MB
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
        // Autorización: solo el diseñador asignado puede iniciar.
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }

        // Validación: no se puede iniciar si ya se inició o no está en el estado correcto.
        if ($designOrder->started_at || $designOrder->status !== 'Autorizada') {
            return back()->with('error', 'Esta orden no se puede iniciar.');
        }

        $designOrder->update(['started_at' => now()]);

        return back()->with('success', 'El trabajo ha sido iniciado.');
    }

    /**
     * Mark the design order as finished and create a design asset.
     *
     * @param  \App\Models\DesignOrder  $designOrder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function finishWork(Request $request, DesignOrder $designOrder)
    {
        // Autorización
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        
        // Validación de estado
        if (!$designOrder->started_at || $designOrder->finished_at) {
            return back()->with('error', 'Esta orden no se puede finalizar.');
        }

        // --- INICIO: NUEVA VALIDACIÓN ---
        // Validar que se hayan subido los archivos finales
        $request->validate([
            'final_files' => 'required|array|min:1',
            'final_files.*' => 'file|max:20480', // Límite de 20MB por archivo, puedes ajustarlo
        ]);
        // --- FIN: NUEVA VALIDACIÓN ---

        // Usar una transacción para asegurar la integridad de los datos
        DB::transaction(function () use ($designOrder, $request) {
            // 1. Crear el activo de diseño
            $design = Design::create([
                'name' => $designOrder->order_title,
                'description' => $designOrder->specifications,
                'design_category_id' => $designOrder->design_category_id,
                'original_design_id' => $designOrder->modifies_design_id,
            ]);

            // --- INICIO: NUEVA LÓGICA DE ARCHIVOS ---
            // 2. Asociar los archivos finales al nuevo activo de diseño
            foreach ($request->file('final_files') as $file) {
                $design->addMedia($file)->toMediaCollection('completed_files');
            }
            // --- FIN: NUEVA LÓGICA DE ARCHIVOS ---

            // 3. Actualizar la orden de diseño
            $designOrder->update([
                'finished_at' => now(),
                'status' => 'Terminada',
                'design_id' => $design->id,
            ]);
        });

        // --- LÓGICA PARA ENVIAR LA NOTIFICACIÓN ---
        $designOrder->load('requester');
        $folio = 'OD-' . str_pad($designOrder->id, 4, "0", STR_PAD_LEFT);
        $designOrder->requester->notify(new DesignOrderFinishedNotification(
            'Orden de Diseño',
            $folio,
            'design_order_finished', // Un tipo para identificarla en el frontend si es necesario
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

}
