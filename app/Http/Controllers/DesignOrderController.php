<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Design;
use App\Models\DesignAssignmentLog;
use App\Models\DesignCategory;
use App\Models\DesignOrder;
use App\Models\User;
use App\Notifications\designOrderAuthorizedNotification;
use App\Notifications\NewDesignOrderAssignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DesignOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = DesignOrder::with(['requester:id,name', 'designer:id,name', 'designCategory:id,name']);

        // Lógica de visibilidad
        if ($request->input('view') === 'all') {
            // No se aplica ningún filtro de usuario, muestra todos
        } 
        // Si el usuario es un diseñador (asumiendo que tiene un rol o permiso específico)
        // Aquí podrías usar: $user->hasRole('Diseñador')
        else if ($user->isDesigner()) { // Deberás crear este método en tu modelo User
            $query->where('designer_id', $user->id);
        }
        // Para cualquier otro caso (solicitantes o gerentes en vista "Míos")
        else {
            $query->where('requester_id', $user->id);
        }

        $designOrders = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Design/Index', [
            'designOrders' => $designOrders,
            'filters' => $request->only(['view']),
        ]);
    }

    public function create()
    {
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

        return Inertia::render('Design/Create', [
            'designCategories' => $designCategories,
            'designers' => $designers,
            'branches' => $branches,
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
            'media.*' => 'file|max:10240' // Límite de 10MB por archivo (10240 KB), puedes ajustarlo
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
        // Cargar todas las órdenes para el selector de búsqueda
        $designOrders = DesignOrder::select('id', 'order_title')->get();

        // Cargar las relaciones necesarias para la vista
        $designOrder->load(['requester:id,name', 'designer:id,name', 'designCategory:id,name', 'media']);

        // // Obtener los archivos asociados a la orden de diseño
        // // Es importante crear una colección de medios 'design_order_files' en tu modelo DesignOrder
        // $files = $designOrder->getMedia('design_order_files')->map(function ($media) {
        //     return [
        //         'id' => $media->id,
        //         'name' => $media->name,
        //         'file_name' => $media->file_name,
        //         'url' => $media->getFullUrl(),
        //         'size' => $media->size,
        //         'mime_type' => $media->mime_type,
        //     ];
        // });

        // return $designOrder;
        return Inertia::render('Design/Show', [
            'designOrder' => $designOrder,
            'designOrders' => $designOrders,
        ]);
    }

    public function edit(DesignOrder $designOrder)
    {
        //
    }

    public function update(Request $request, DesignOrder $designOrder)
    {
        //
    }

    public function destroy(DesignOrder $designOrder)
    {
        //
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
        if ($designOrder->started_at || $designOrder->status !== 'En proceso') {
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
    public function finishWork(DesignOrder $designOrder)
    {
        // Autorización
        if (Auth::id() !== $designOrder->designer_id) {
            abort(403, 'No tienes permiso para realizar esta acción.');
        }
        
        // Validación
        if (!$designOrder->started_at || $designOrder->finished_at) {
            return back()->with('error', 'Esta orden no se puede finalizar.');
        }

        // Usar una transacción para asegurar la integridad de los datos
        DB::transaction(function () use ($designOrder) {
            // 1. Crear el activo de diseño
            $design = Design::create([
                'name' => $designOrder->order_title,
                'description' => $designOrder->specifications,
                'design_category_id' => $designOrder->design_category_id,
            ]);

            // 2. Actualizar la orden de diseño
            $designOrder->update([
                'finished_at' => now(),
                'status' => 'Terminada',
                'design_id' => $design->id,
            ]);
        });

        return back()->with('success', 'Diseño finalizado y archivado correctamente.');
    }

    /**
     * Sube archivos y los asocia a una orden de diseño.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DesignOrder  $designOrder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function upload(Request $request, DesignOrder $designOrder)
    {
        $request->validate([
            'files.*' => 'required|file|max:20480', // Límite de 20MB por archivo
        ]);

        foreach ($request->file('files') as $file) {
            $designOrder->addMedia($file)->toMediaCollection('design_order_files');
        }

        return back()->with('success', 'Archivos subidos correctamente.');
    }

    /**
     * Elimina un archivo multimedia de una orden de diseño.
     *
     * @param  \App\Models\DesignOrder  $designOrder
     * @param  \Spatie\MediaLibrary\MediaCollections\Models\Media  $media
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteMedia(DesignOrder $designOrder, Media $media)
    {
        // Opcional: añadir autorización para asegurar que el usuario puede borrar
        // if (Auth::id() !== $designOrder->requester_id && Auth::id() !== $designOrder->designer_id) {
        //     abort(403);
        // }

        $media->delete();

        return back()->with('success', 'Archivo eliminado.');
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
        // if (auth()->id() != $designOrder->requester->id) {
            // Generamos un folio legible para la notificación
            $designOrder_folio = 'OD-' . str_pad($designOrder->id, 4, "0", STR_PAD_LEFT);
            
            // Enviamos la notificación al usuario que creó la venta
            $designOrder->requester->notify(new designOrderAuthorizedNotification(
                'Orden autorizada', // Título de la notificación
                $designOrder_folio, // Folio para mostrar
                route('design-orders.show', $designOrder->id) // URL para redirigir al usuario
            ));
        // }

        return response()->json(['message' => 'Orden autorizada', 'item' => $designOrder]);
    }
}
