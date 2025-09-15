<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Design;
use App\Models\DesignAuthorization;
use App\Models\DesignOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DesignAuthorizationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search']);
        
        $authorizations = DesignAuthorization::query()
            // Cargar relaciones para evitar N+1 queries en la vista
            ->with(['contact:id,name', 'seller:id,name', 'branch:id,name'])
            // Aplicar filtro de búsqueda si existe
            ->when($request->input('search'), function ($query, $search) {
                $query->where('product_name', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('seller', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->latest() // Ordenar por los más recientes primero
            ->paginate(15)
            ->withQueryString(); // Para que la paginación conserve los filtros

        return Inertia::render('DesignAuthorization/Index', [
            'authorizations' => $authorizations,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        // Solo se obtienen los datos básicos de las órdenes de diseño.
        $designOrders = DesignOrder::whereDoesntHave('designAuthorization')
            ->select('id', 'order_title')
            ->get();

        $design_order_id = null;
        $design_order = null;
        if ($request->has('design_order_id')) {
            $design_order_id = intval($request->input('design_order_id'));
            $design_order = DesignOrder::find($design_order_id);
        }
        
        // Asumiendo que todos los usuarios pueden ser vendedores.
        // Podrías filtrar por un rol si lo tuvieras.
        $sellers = User::select('id', 'name')->role('Vendedor')->get(); 
        $branches = Branch::with('contacts:id,name,charge,branch_id')->select('id', 'name')->get();

        return Inertia::render('DesignAuthorization/Create', [
            'designOrders' => $designOrders,
            'sellers' => $sellers,
            'branches' => $branches,
            'design_order' => $design_order, // orden de diseño relacionada 
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // --- Validación mejorada ---
        $validated = $request->validate([
            'design_order_id' => 'required|exists:design_orders,id|unique:design_authorizations,design_order_id',
            'product_name' => 'required|string|max:255',
            'material' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'production_methods' => 'nullable|array',
            'specifications' => 'nullable|string',
            'seller_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            
            // 'cover_media_id' es opcional, pero debe existir en la tabla 'media' si se envía.
            'cover_media_id' => 'nullable|exists:media,id', 
            
            // 'media' es requerido solo si 'cover_media_id' no está presente.
            'media' => 'required_without:cover_media_id|nullable|array', 
            'media.*' => 'file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max per file
        ]);

        // --- Creación del registro ---
        $authorization = DesignAuthorization::create($validated);

        // --- Lógica para manejar la imagen de portada ---

        // Opción 1: Se proporcionó un ID de un medio existente.
        if ($request->filled('cover_media_id')) {
            $mediaToCopy = Media::find($validated['cover_media_id']);
            if ($mediaToCopy) {
                // Copia el medio existente a la colección 'cover' de la nueva autorización.
                $mediaToCopy->copy($authorization, 'cover');
            }
        
        // Opción 2: No se proporcionó un ID, así que se suben nuevos archivos.
        } elseif ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                // Agrega los nuevos archivos directamente a la colección 'cover'.
                $authorization->addMedia($file)->toMediaCollection('cover');
            }
        }

        // --- Redirección ---
        return to_route('design-authorizations.index')
            ->with('message', 'Autorización creada correctamente.');
    }

    public function show(DesignAuthorization $designAuthorization)
    {
        // Cargar todas las relaciones necesarias para la vista
        $designAuthorization->load(['designOrder:id,order_title', 'seller:id,name', 'branch:id,name', 'contact:id,name']);

        // Obtener la imagen de portada (de la colección 'cover')
        $cover = $designAuthorization->getFirstMedia('cover');
        // Obtener los archivos adicionales (de la colección 'default')
        $additionalFiles = $designAuthorization->getMedia('default');

        return Inertia::render('DesignAuthorization/Show', [
            'authorization' => $designAuthorization,
            // Pasamos la URL de la portada y los demás archivos de forma explícita
            'cover_image_url' => $cover ? $cover->getFullUrl() : null,
            'additional_files' => $additionalFiles->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->file_name,
                'url' => $file->getFullUrl(),
                'mime_type' => $file->mime_type,
            ]),
        ]);
    }

    public function edit(DesignAuthorization $designAuthorization)
    {
        // Cargar la portada para obtener su ID
        $cover = $designAuthorization->getFirstMedia('cover');

        // Obtener las órdenes de diseño que no tienen autorización,
        // o la que corresponde a la autorización que se está editando.
        $designOrders = DesignOrder::whereDoesntHave('designAuthorization')
            ->orWhere('id', $designAuthorization->design_order_id)
            ->select('id', 'order_title')
            ->get();

        $sellers = User::select('id', 'name')->role('Vendedor')->get();
        $branches = Branch::with('contacts:id,name,charge,branch_id')->select('id', 'name')->get();

        return Inertia::render('DesignAuthorization/Edit', [
            'authorization' => $designAuthorization,
            'designOrders' => $designOrders,
            'sellers' => $sellers,
            'branches' => $branches,
            'cover_image_url' => $cover ? $cover->getFullUrl() : null,
            'current_cover_id' => $cover ? $cover->id : null,
        ]);
    }

    public function update(Request $request, DesignAuthorization $designAuthorization)
    {
        // --- Validación para la actualización ---
        $validated = $request->validate([
            // design_order_id no se valida porque no se puede cambiar
            'product_name' => 'required|string|max:255',
            'material' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'production_methods' => 'nullable|array',
            'specifications' => 'nullable|string',
            'seller_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            'cover_media_id' => 'nullable|exists:media,id',
        ]);

        // --- Actualización del registro ---
        $designAuthorization->update($validated);

        // --- Lógica para actualizar la imagen de portada ---
        // Si se envió un nuevo ID de imagen de portada.
        if ($request->filled('cover_media_id')) {
            $currentCover = $designAuthorization->getFirstMedia('cover');
            
            // Solo proceder si la nueva imagen es diferente a la actual.
            if (!$currentCover || $currentCover->id != $validated['cover_media_id']) {
                // Eliminar la portada anterior.
                $designAuthorization->clearMediaCollection('cover');

                // Copiar el nuevo medio a la colección 'cover'.
                $mediaToCopy = Media::find($validated['cover_media_id']);
                if ($mediaToCopy) {
                    $mediaToCopy->copy($designAuthorization, 'cover');
                }
            }
        }

        // --- Redirección ---
        return to_route('design-authorizations.index')
            ->with('message', 'Autorización actualizada correctamente.');
    }

    public function destroy(DesignAuthorization $designAuthorization)
    {
        $designAuthorization->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $designAuthorization = DesignAuthorization::find($id);
            $designAuthorization?->delete();
        }
    }

    /**
     * Obtiene los archivos del diseño final asociado a una orden de diseño.
     */
    public function getDesignOrderFiles(DesignOrder $designOrder)
    {
        // Cargar la relación 'design' para acceder al diseño final de la orden.
        $design = $designOrder->design;

        // Verificar si la orden tiene un diseño asociado.
        if (!$design) {
            return response()->json([]);
        }

        // Accedemos a la propiedad del accesor 'media' directamente.
        // Esta es la misma propiedad que ves cuando retornas el modelo Design completo.
        $mediaItems = $design->media;

        // Mapeamos la colección de medios al formato que necesita el frontend.
        $files = $mediaItems->map(function ($media) {
            return [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'original_url' => $media->getFullUrl(),
            ];
        });

        return response()->json($files);
    }

    /**
     * Update the client's response for the authorization.
     */
    public function updateClientResponse(Request $request, DesignAuthorization $designAuthorization)
    {
        $validated = $request->validate([
            'is_accepted' => 'required|boolean',
            'rejection_reason' => 'nullable|string|required_if:is_accepted,false',
        ]);

        $designAuthorization->update([
            'is_accepted' => $validated['is_accepted'],
            'rejection_reason' => $validated['is_accepted'] ? null : $validated['rejection_reason'],
            'responded_at' => now(),
        ]);

        return back()->with('message', 'Respuesta del cliente actualizada correctamente.');
    }

    /**
     * Mark the authorization as internally approved with the authorizer's name.
     */
    public function authorizeInternal(DesignAuthorization $designAuthorization)
    {
        $designAuthorization->update([
            'authorizer_name' => auth()->user()->name,
        ]);

        return back()->with('message', 'El formato ha sido autorizado internamente.');
    }

    public function print(DesignAuthorization $designAuthorization)
    {
        // Cargar todas las relaciones necesarias para la vista
        $designAuthorization->load(['designOrder:id,order_title', 'seller:id,name', 'branch:id,name', 'contact:id,name']);

        // Obtener la imagen de portada (de la colección 'cover')
        $cover = $designAuthorization->getFirstMedia('cover');
        // Obtener los archivos adicionales (de la colección 'default')
        $additionalFiles = $designAuthorization->getMedia('default');

        return Inertia::render('DesignAuthorization/Print', [
            'authorization' => $designAuthorization,
            // Pasamos la URL de la portada y los demás archivos de forma explícita
            'cover_image_url' => $cover ? $cover->getFullUrl() : null,
            'additional_files' => $additionalFiles->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->file_name,
                'url' => $file->getFullUrl(),
                'mime_type' => $file->mime_type,
            ]),
        ]);;
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $purchases = DesignAuthorization::with(['contact:id,name', 'seller:id,name', 'branch:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhere('product_name', 'like', "%{$query}%")
                ->orWhereHas('seller', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $purchases], 200);
    }
}
