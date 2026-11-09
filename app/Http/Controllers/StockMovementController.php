<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    /**
     * Historial de entradas y salidas de inventario.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->checkPermissionTo('Ver almacenes'), 403);

        $filters = [
            'search' => is_string($request->input('search')) ? trim($request->input('search')) : null,
            'type' => in_array($request->input('type'), ['Entrada', 'Salida'], true) ? $request->input('type') : null,
            'date_from' => $this->parseDate($request->input('date_from')),
            'date_to' => $this->parseDate($request->input('date_to')),
            'sort' => $request->input('sort') === 'oldest' ? 'oldest' : 'recent',
            'without_components' => $request->boolean('without_components'),
        ];

        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        $query = StockMovement::query()
            ->when($filters['type'], function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($filters['date_from'], function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'], function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
                    })->orWhere('notes', 'LIKE', "%{$search}%");
                });
            })
            ->when($filters['without_components'], function ($query) {
                // Excluye productos ensamblados, tanto con componentes propios como heredados del padre.
                $query->whereHas('product', function ($productQuery) {
                    $productQuery->whereDoesntHave('components')
                        ->whereDoesntHave('parent.components');
                });
            });

        // Productos distintos involucrados en todos los movimientos que cumplen los filtros.
        $productsCount = (clone $query)->distinct()->count('product_id');

        $movements = $query
            ->with([
                'product:id,code,name,measure_unit,parent_id',
                'product.media',
                'storage:id,location,quantity',
            ])
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('StockMovement/Index', [
            'movements' => $movements,
            'productsCount' => $productsCount,
            'filters' => $filters,
            'canDelete' => $user->checkPermissionTo('Eliminar movimientos de stock'),
        ]);
    }

    /**
     * Elimina un movimiento y revierte su efecto en el stock del almacén.
     */
    public function destroy(Request $request, StockMovement $stockMovement)
    {
        $user = $request->user();
        abort_unless($user && $user->checkPermissionTo('Ver almacenes'), 403);
        abort_unless($user->checkPermissionTo('Eliminar movimientos de stock'), 403);

        $clamped = DB::transaction(function () use ($stockMovement) {
            return $this->deleteWithStockReversal([$stockMovement->id]);
        });

        if ($clamped) {
            return back()->with('warning', $this->stockClampedMessage($clamped));
        }

        return back();
    }

    /**
     * Elimina varios movimientos a la vez, revirtiendo su efecto en el stock.
     */
    public function destroyBulk(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->checkPermissionTo('Ver almacenes'), 403);
        abort_unless($user->checkPermissionTo('Eliminar movimientos de stock'), 403);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:stock_movements,id'],
        ], [
            'ids.required' => 'Selecciona al menos un movimiento.',
            'ids.*.exists' => 'Uno de los movimientos seleccionados ya no existe.',
        ]);

        $clamped = DB::transaction(function () use ($validated) {
            return $this->deleteWithStockReversal($validated['ids']);
        });

        if ($clamped) {
            return back()->with('warning', $this->stockClampedMessage($clamped));
        }

        return back();
    }

    /**
     * Elimina los movimientos indicados revirtiendo el stock de cada almacén.
     * Se aplica el efecto neto por almacén para soportar entradas y salidas combinadas.
     *
     * @return array<int, array{product: string, stock: float, adjustment: float}>
     */
    private function deleteWithStockReversal(array $ids): array
    {
        $movements = StockMovement::query()
            ->whereIn('id', $ids)
            ->with('product:id,name')
            ->lockForUpdate()
            ->get();

        if ($movements->isEmpty()) {
            throw ValidationException::withMessages([
                'stock' => 'No se encontraron los movimientos seleccionados.',
            ]);
        }

        $adjustments = [];

        foreach ($movements as $movement) {
            // Algunos módulos guardan la cantidad en negativo para las salidas.
            $quantity = abs((float) $movement->quantity_change);

            // Al eliminar una entrada se resta del stock; al eliminar una salida se devuelve.
            $adjustments[$movement->storage_id] = ($adjustments[$movement->storage_id] ?? 0)
                + ($movement->type === 'Entrada' ? -$quantity : $quantity);
        }

        $storages = Storage::query()
            ->whereIn('id', array_keys($adjustments))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $clamped = [];

        foreach ($adjustments as $storageId => $adjustment) {
            $storage = $storages->get($storageId);

            if (!$storage) {
                throw ValidationException::withMessages([
                    'stock' => 'Uno de los movimientos no tiene un almacén asociado.',
                ]);
            }

            $newQuantity = (float) $storage->quantity + $adjustment;

            // El stock nunca puede ser negativo: si el ajuste excede la existencia, se deja en 0.
            if ($newQuantity < 0) {
                $clamped[] = [
                    'product' => optional($movements->firstWhere('storage_id', $storageId)->product)->name
                        ?? 'producto desconocido',
                    'stock' => (float) $storage->quantity,
                    'adjustment' => $adjustment,
                ];

                $newQuantity = 0;
            }

            $storage->update(['quantity' => $newQuantity]);
        }

        StockMovement::whereIn('id', $movements->pluck('id'))->delete();

        return $clamped;
    }

    /**
     * Mensaje de advertencia cuando el ajuste de stock tuvo que limitarse a cero.
     */
    private function stockClampedMessage(array $clamped): string
    {
        $products = collect($clamped)
            ->map(function ($item) {
                return '"' . $item['product'] . '" (existencia: ' . $this->formatQuantity($item['stock'])
                    . ', ajuste: ' . $this->formatQuantity($item['adjustment']) . ')';
            })
            ->unique()
            ->implode(', ');

        return 'Movimiento(s) eliminado(s). El stock de ' . $products
            . ' quedó en 0 porque el ajuste superaba la existencia disponible; el stock no puede ser negativo.';
    }

    /**
     * Formatea una cantidad sin decimales innecesarios para los mensajes de advertencia.
     */
    private function formatQuantity(float $value): string
    {
        $rounded = round($value, 2);

        return fmod($rounded, 1.0) === 0.0
            ? number_format($rounded, 0, '.', ',')
            : number_format($rounded, 2, '.', ',');
    }

    /**
     * Valida que la fecha recibida tenga el formato Y-m-d; de lo contrario se ignora el filtro.
     */
    private function parseDate($value): ?string
    {
        if (!is_string($value) || !$value) {
            return null;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
