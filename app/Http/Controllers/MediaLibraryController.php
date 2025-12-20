<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\Design;
use App\Models\DesignOrder;
use App\Models\Format; // Nuevo modelo
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only('search', 'branch_id', 'tab');
        $searchTerm = $filters['search'] ?? null;

        // --- 1. Diseños Terminados ---
        $completedFilesQuery = Media::query()
            ->where('collection_name', 'completed_files')
            ->where('model_type', Design::class);

        // --- 2. Recursos de Órdenes ---
        $designOrderFilesQuery = Media::query()
            ->where('collection_name', 'design_order_files')
            ->where('model_type', DesignOrder::class)
            ->with('model.branch');

        // --- 3. Formatos Públicos (Frecuentes) ---
        $publicFormatsQuery = Media::query()
            ->where('model_type', Format::class)
            ->whereHasMorph('model', [Format::class], function ($query) {
                $query->where('is_restricted', false);
            });

        // --- 4. Formatos Restringidos (Con Permisos) ---
        $restrictedFormatsQuery = Media::query()
            ->where('model_type', Format::class)
            ->whereHasMorph('model', [Format::class], function ($query) {
                $query->where('is_restricted', true);
            });

        // Aplicar filtros globales
        if ($searchTerm) {
            $completedFilesQuery->where('name', 'like', '%' . $searchTerm . '%');
            $designOrderFilesQuery->where('name', 'like', '%' . $searchTerm . '%');
            $publicFormatsQuery->where('name', 'like', '%' . $searchTerm . '%');
            $restrictedFormatsQuery->where('name', 'like', '%' . $searchTerm . '%');
        }

        if ($branchId = $filters['branch_id'] ?? null) {
            // Filtrar recursos de órdenes
            $designOrderFilesQuery->whereHasMorph('model', [DesignOrder::class], function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });

            // Filtrar diseños terminados
            $designIdsForBranch = DesignOrder::where('branch_id', $branchId)->whereNotNull('design_id')->pluck('design_id');
            $completedFilesQuery->whereIn('model_id', $designIdsForBranch);
        }

        return Inertia::render('MediaLibrary/Index', [
            'completedFiles' => $this->paginateAndFormat($completedFilesQuery, 'completed_page', 'design'),
            'designOrderFiles' => $this->paginateAndFormat($designOrderFilesQuery, 'resources_page', 'order'),
            'publicFormats' => $this->paginateAndFormat($publicFormatsQuery, 'public_page', 'format'),
            'restrictedFormats' => $this->paginateAndFormat($restrictedFormatsQuery, 'restricted_page', 'format'),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB
            'name' => 'required|string|max:255',
            'is_restricted' => 'required|boolean',
        ]);

        // Crear el registro padre "Format"
        $format = Format::create([
            'name' => $request->name,
            'is_restricted' => $request->is_restricted,
        ]);

        // Adjuntar archivo con Spatie Media Library
        $format->addMedia($request->file('file'))
            ->usingName($request->name)
            ->toMediaCollection('formats');

        return redirect()->back()->with('success', 'Formato subido correctamente.');
    }

    public function destroy($mediaId)
    {
        // Validación de permisos (Backend)
        // if (! auth()->user()->can('Eliminar formatos')) { abort(403); }

        $media = Media::findOrFail($mediaId);
        
        // Si el archivo pertenece a un "Format", eliminamos el modelo padre para no dejar basura
        if ($media->model_type === Format::class) {
            $media->model->delete(); // Esto elimina el registro en 'formats' y el archivo
        } else {
            $media->delete();
        }

        return redirect()->back()->with('success', 'Archivo eliminado correctamente.');
    }

    // --- Helpers ---

    protected function paginateAndFormat($query, $pageName, $type)
    {
        return $query->latest()
            ->paginate(15, ['*'], $pageName)
            ->withQueryString()
            ->through(fn ($media) => $this->formatMedia($media, $type));
    }

    protected function formatMedia(Media $media, string $type): array
    {
        $additionalData = [];

        if ($type === 'design') {
            $designOrder = DesignOrder::where('design_id', $media->model_id)->with('branch')->first();
            $additionalData['branch_name'] = $designOrder?->branch?->name;
        } elseif ($type === 'order') {
            $additionalData = [
                'order_title' => $media->model?->order_title,
                'requester' => $media->model?->requester?->name,
                'branch_name' => $media->model?->branch?->name,
            ];
        }

        return array_merge([
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'original_url' => $media->getUrl(),
            'preview_url' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : $media->getUrl(),
            'created_at' => $media->created_at->isoFormat('D MMM, YYYY'),
            'model_name' => class_basename($media->model_type),
            'model_id' => $media->model_id,
        ], $additionalData);
    }
}