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
        $designOrders = DesignOrder::whereDoesntHave('designAuthorization')->latest()
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
        
        // CORRECCIÓN: Usar contactable_id y contactable_type en lugar de branch_id
        $branches = Branch::with('contacts:id,name,charge,contactable_id,contactable_type')->select('id', 'name')->get();

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
        // return $request->hasFile('pantone_media');
        // --- Validación mejorada ---
        $validated = $request->validate([
            'design_order_id' => 'required|exists:design_orders,id|unique:design_authorizations,design_order_id',
            'version' => 'nullable|string|max:50',
            'product_name' => 'required|string|max:255',
            'product_type' => 'nullable|string|max:255', 
            'material' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            // MODIFICADO: Validación para aceptar un arreglo de pantones
            'pantone' => 'nullable|array', 
            'pantone.*.name' => 'required|string|max:255',
            'pantone.*.color' => 'required|string|max:50',
            'pantone_color' => 'nullable|string|max:50',
            'pantone_media' => 'nullable|array', 
            'pantone_media.*' => 'file|mimes:jpg,jpeg,png|max:2048',
            'dimensions' => 'nullable|string|max:255',
            'production_methods' => 'nullable|array',
            'specifications' => 'nullable|string',
            'delivery_time' => 'nullable|string|max:255',
            'minimum_volume' => 'nullable|integer|min:0',
            'printing_tooling_cost' => 'nullable|numeric|min:0',
            'injection_tooling_cost' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'freight_cost' => 'nullable|numeric|min:0',
            'seller_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            
            'cover_media_id' => 'nullable|exists:media,id', 
            'media' => 'required_without:cover_media_id|nullable|array', 
            'media.*' => 'file|mimes:jpg,jpeg,png,pdf',
        ]);

        // --- Creación del registro ---
        $authorization = DesignAuthorization::create($validated);

        // --- Lógica para manejar la imagen de portada ---
        if ($request->filled('cover_media_id')) {
            $mediaToCopy = Media::find($validated['cover_media_id']);
            if ($mediaToCopy) {
                $mediaToCopy->copy($authorization, 'cover');
            }
        } elseif ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $authorization->addMedia($file)->toMediaCollection('cover');
            }
        }

        // --- Lógica para manejar la captura de pantones ---
        if ($request->hasFile('pantone_media')) {
            foreach ($request->file('pantone_media') as $file) {
                $authorization->addMedia($file)->toMediaCollection('pantone_capture');
            }
        }

        return to_route('design-authorizations.index')
            ->with('message', 'Autorización creada correctamente.');
    }

    public function show(DesignAuthorization $designAuthorization)
    {
        $designAuthorization->load(['designOrder:id,order_title', 'seller:id,name', 'branch:id,name', 'contact:id,name']);

        $cover = $designAuthorization->getFirstMedia('cover');
        $pantoneCapture = $designAuthorization->getFirstMedia('pantone_capture');
        $additionalFiles = $designAuthorization->getMedia('default');

        return Inertia::render('DesignAuthorization/Show', [
            'authorization' => $designAuthorization,
            'cover_image_url' => $cover ? $cover->getFullUrl() : null,
            'pantone_capture_url' => $pantoneCapture ? $pantoneCapture->getFullUrl() : null,
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
        $cover = $designAuthorization->getFirstMedia('cover');
        $pantoneCapture = $designAuthorization->getFirstMedia('pantone_capture');

        $designOrders = DesignOrder::whereDoesntHave('designAuthorization')
            ->orWhere('id', $designAuthorization->design_order_id)
            ->select('id', 'order_title')
            ->get();

        $sellers = User::select('id', 'name')->role('Vendedor')->get();
        
        $branches = Branch::with('contacts:id,name,charge,contactable_id,contactable_type')->select('id', 'name')->get();

        return Inertia::render('DesignAuthorization/Edit', [
            'authorization' => $designAuthorization,
            'designOrders' => $designOrders,
            'sellers' => $sellers,
            'branches' => $branches,
            'cover_image_url' => $cover ? $cover->getFullUrl() : null,
            'pantone_capture_url' => $pantoneCapture ? $pantoneCapture->getFullUrl() : null,
            'current_cover_id' => $cover ? $cover->id : null,
        ]);
    }

    public function update(Request $request, DesignAuthorization $designAuthorization)
    {
        // --- Validación para la actualización ---
        $validated = $request->validate([
            'version' => 'nullable|string|max:50',
            'product_name' => 'required|string|max:255',
            'product_type' => 'nullable|string|max:255', 
            'material' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            // MODIFICADO: Validación para aceptar un arreglo de pantones
            'pantone' => 'nullable|array', 
            'pantone.*.name' => 'required|string|max:255',
            'pantone.*.color' => 'required|string|max:50',
            'pantone_color' => 'nullable|string|max:50',
            'pantone_media' => 'nullable|array', 
            'pantone_media.*' => 'file|mimes:jpg,jpeg,png|max:2048',
            'dimensions' => 'nullable|string|max:255',
            'production_methods' => 'nullable|array',
            'specifications' => 'nullable|string',
            'delivery_time' => 'nullable|string|max:255',
            'minimum_volume' => 'nullable|integer|min:0',
            'printing_tooling_cost' => 'nullable|numeric|min:0',
            'injection_tooling_cost' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'freight_cost' => 'nullable|numeric|min:0',
            'seller_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'contact_id' => 'required|exists:contacts,id',
            'cover_media_id' => 'nullable|exists:media,id',
        ]);

        // --- Actualización del registro ---
        $designAuthorization->update($validated);

        // --- Lógica para actualizar la imagen de portada ---
        if ($request->filled('cover_media_id')) {
            $currentCover = $designAuthorization->getFirstMedia('cover');
            
            if (!$currentCover || $currentCover->id != $validated['cover_media_id']) {
                $designAuthorization->clearMediaCollection('cover');

                $mediaToCopy = Media::find($validated['cover_media_id']);
                if ($mediaToCopy) {
                    $mediaToCopy->copy($designAuthorization, 'cover');
                }
            }
        }

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
            'authorized_at' => now(),
        ]);

        return back()->with('message', 'El formato ha sido autorizado internamente.');
    }

    public function print(DesignAuthorization $designAuthorization)
    {
        // Cargar todas las relaciones necesarias para la vista
        $designAuthorization->load(['designOrder:id,order_title', 'seller:id,name', 'branch:id,name', 'contact:id,name']);

        // Obtener la imagen de portada (de la colección 'cover')
        $cover = $designAuthorization->getFirstMedia('cover');
        // Obtener captura de pantones si existe
        $pantoneCapture = $designAuthorization->getFirstMedia('pantone_capture');
        // Obtener los archivos adicionales (de la colección 'default')
        $additionalFiles = $designAuthorization->getMedia('default');

        return Inertia::render('DesignAuthorization/Print', [
            'authorization' => $designAuthorization,
            // Pasamos la URL de la portada y los demás archivos de forma explícita
            'cover_image_url' => $cover ? $cover->getFullUrl() : null,
            'pantone_capture_url' => $pantoneCapture ? $pantoneCapture->getFullUrl() : null,
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