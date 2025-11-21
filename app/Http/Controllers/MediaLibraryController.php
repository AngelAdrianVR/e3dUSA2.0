<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\Design;
use App\Models\DesignOrder;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only('search', 'branch_id', 'tab');

        // --- Obtener archivos de Diseños Terminados (completed_files) ---
        $completedFilesQuery = Media::query()
            ->where('collection_name', 'completed_files')
            ->where('model_type', Design::class);

        // --- Obtener archivos de Recursos de Órdenes de Diseño (design_order_files) ---
        $designOrderFilesQuery = Media::query()
            ->where('collection_name', 'design_order_files')
            ->where('model_type', DesignOrder::class)
            ->with('model.branch'); // Eager load for efficiency

        // Aplicar filtros
        if ($searchTerm = $filters['search'] ?? null) {
            $completedFilesQuery->where('name', 'like', '%' . $searchTerm . '%');
            $designOrderFilesQuery->where('name', 'like', '%' . $searchTerm . '%');
        }

        if ($branchId = $filters['branch_id'] ?? null) {
            // Filtrar recursos de órdenes directamente
            $designOrderFilesQuery->whereHasMorph('model', [DesignOrder::class], function ($query) use ($branchId) {
                $query->where('branch_id', $branchId);
            });

            // Para diseños terminados, encontrar los design_id asociados a la sucursal
            $designIdsForBranch = DesignOrder::where('branch_id', $branchId)->whereNotNull('design_id')->pluck('design_id');
            $completedFilesQuery->whereIn('model_id', $designIdsForBranch);
        }

        // Paginar y formatear resultados con nombres de página únicos
        $completedFiles = $completedFilesQuery->latest()
            ->paginate(15, ['*'], 'completed_page') // Nombre de página para esta pestaña
            ->withQueryString()
            ->through(function ($media) {
                 // Find the related DesignOrder to get branch info
                $designOrder = DesignOrder::where('design_id', $media->model_id)->with('branch')->first();
                return $this->formatMedia($media, [
                    'branch_name' => $designOrder?->branch?->name,
                ]);
            });

        $designOrderFiles = $designOrderFilesQuery->latest()
            ->paginate(15, ['*'], 'resources_page') // Nombre de página para esta pestaña
            ->withQueryString()
            ->through(function ($media) {
                return $this->formatMedia($media, [
                    'order_title' => $media->model?->order_title,
                    'requester' => $media->model?->requester?->name,
                    'branch_name' => $media->model?->branch?->name,
                ]);
            });

        return Inertia::render('MediaLibrary/Index', [
            'completedFiles' => $completedFiles,
            'designOrderFiles' => $designOrderFiles,
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    /**
     * Formatea el objeto de media para la vista.
     */
    protected function formatMedia(Media $media, array $additionalData = []): array
    {
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
