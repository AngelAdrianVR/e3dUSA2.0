<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use App\Models\Design;
use App\Models\DesignOrder;
use App\Models\Format; // Nuevo modelo
use App\Models\User;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaLibraryController extends Controller
{
    /**
     * Estatus del flujo de una orden de diseño.
     */
    public const ORDER_STATUSES = ['Pendiente', 'Autorizada', 'En proceso', 'Terminada', 'Cancelada'];

    public function index(Request $request)
    {
        $filters = $request->only('search', 'branch_id', 'tab', 'sort', 'status', 'designer_id');
        $searchTerm = $filters['search'] ?? null;
        $sort = $filters['sort'] ?? null;
        $branchId = $filters['branch_id'] ?? null;
        $status = $filters['status'] ?? null;
        $designerId = $filters['designer_id'] ?? null;

        // Filtros que aplican a la orden de diseño dueña del archivo
        $applyOrderFilters = function ($query) use ($branchId, $status, $designerId) {
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($designerId) {
                $query->where('designer_id', $designerId);
            }
        };

        $hasOrderFilters = (bool) ($branchId || $status || $designerId);

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

        if ($hasOrderFilters) {
            // Filtrar recursos de órdenes
            $designOrderFilesQuery->whereHasMorph('model', [DesignOrder::class], $applyOrderFilters);

            // Filtrar diseños terminados
            $orderDesignIds = DesignOrder::query()
                ->select('design_id')
                ->whereNotNull('design_id');
            $applyOrderFilters($orderDesignIds);

            $completedFilesQuery->whereIn('model_id', $orderDesignIds);
        }

        return Inertia::render('MediaLibrary/Index', [
            'completedFiles' => $this->paginateAndFormat($completedFilesQuery, 'completed_page', 'design', $sort),
            'designOrderFiles' => $this->paginateAndFormat($designOrderFilesQuery, 'resources_page', 'order', $sort),
            'publicFormats' => $this->paginateAndFormat($publicFormatsQuery, 'public_page', 'format', $sort),
            'restrictedFormats' => $this->paginateAndFormat($restrictedFormatsQuery, 'restricted_page', 'format', $sort),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'designers' => $this->libraryDesigners(),
            'statusOptions' => self::ORDER_STATUSES,
            'filters' => (object) $filters,
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

    public function massiveDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:media,id',
        ]);

        $mediaItems = Media::whereIn('id', $request->input('ids'))->get();

        foreach ($mediaItems as $media) {
            // Si el archivo pertenece a un "Format", eliminamos el modelo padre para no dejar basura
            if ($media->model_type === Format::class && $media->model) {
                $media->model->delete();
            } else {
                $media->delete();
            }
        }

        $deleted = $mediaItems->count();

        return redirect()->back()->with('success', "{$deleted} archivo(s) eliminado(s) correctamente.");
    }

    // --- Helpers ---

    protected function paginateAndFormat($query, $pageName, $type, ?string $sort = null)
    {
        if ($sort === 'size_asc') {
            $query->orderBy('size', 'asc');
        } elseif ($sort === 'size_desc') {
            $query->orderBy('size', 'desc');
        } else {
            $query->latest();
        }

        $paginator = $query->paginate(15, ['*'], $pageName)->withQueryString();

        $orders = $this->relatedOrders($paginator->getCollection(), $type);

        return $paginator->through(fn ($media) => $this->formatMedia($media, $type, $orders));
    }

    /**
     * Órdenes de diseño de los archivos de la página actual, indexadas por
     * model_id (recursos de orden) o por design_id (diseños terminados).
     */
    protected function relatedOrders($mediaCollection, string $type)
    {
        if ($type === 'format') {
            return collect();
        }

        $column = $type === 'design' ? 'design_id' : 'id';
        $modelIds = $mediaCollection->pluck('model_id')->unique()->values();

        return DesignOrder::with('branch:id,name', 'designer:id,name')
            ->whereIn($column, $modelIds)
            ->orderBy('id')
            ->get()
            ->unique($column)
            ->keyBy($column);
    }

    protected function formatMedia(Media $media, string $type, $orders = null): array
    {
        $additionalData = [];

        if ($type === 'design') {
            $order = $orders?->get($media->model_id);

            $additionalData = [
                'branch_name' => $order?->branch?->name,
                ...$this->orderData($order),
            ];
        } elseif ($type === 'order') {
            $order = $orders?->get($media->model_id);

            $additionalData = [
                'order_title' => $media->model?->order_title,
                'requester' => $media->model?->requester?->name,
                'branch_name' => $media->model?->branch?->name,
                ...$this->orderData($order),
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

    /**
     * Folio, estatus y diseñador de la orden de diseño del archivo.
     */
    protected function orderData(?DesignOrder $order): array
    {
        return [
            'order_folio' => $order ? 'OD-' . str_pad((string) $order->id, 4, '0', STR_PAD_LEFT) : null,
            'order_status' => $order?->status,
            'order_designer' => $order?->designer?->name,
            'order_url' => $order ? route('design-orders.show', $order->id) : null,
        ];
    }

    /**
     * Diseñadores con al menos un archivo en la biblioteca, para el filtro.
     */
    protected function libraryDesigners()
    {
        $designIds = Media::query()
            ->where('collection_name', 'completed_files')
            ->select('model_id');

        $orderIds = Media::query()
            ->where('collection_name', 'design_order_files')
            ->select('model_id');

        $designerIds = DesignOrder::query()
            ->whereNotNull('designer_id')
            ->where(function ($query) use ($designIds, $orderIds) {
                $query->whereIn('design_id', $designIds)
                    ->orWhereIn('id', $orderIds);
            })
            ->select('designer_id');

        return User::query()
            ->whereIn('id', $designerIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
