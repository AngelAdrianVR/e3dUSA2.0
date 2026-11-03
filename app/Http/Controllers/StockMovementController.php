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
        ];

        $direction = $filters['sort'] === 'oldest' ? 'asc' : 'desc';

        $movements = StockMovement::query()
            ->with([
                'product:id,code,name,measure_unit',
                'product.media',
                'storage:id,location',
            ])
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
                $query->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('StockMovement/Index', [
            'movements' => $movements,
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

        DB::transaction(function () use ($stockMovement) {
            $this->deleteWithStockReversal([$stockMovement->id]);
        });

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

        DB::transaction(function () use ($validated) {
            $this->deleteWithStockReversal($validated['ids']);
        });

        return back();
    }

    /**
     * Elimina los movimientos indicados revirtiendo el stock de cada almacén.
     * Se aplica el efecto neto por almacén para soportar entradas y salidas combinadas.
     */
    private function deleteWithStockReversal(array $ids): void
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

        foreach ($adjustments as $storageId => $adjustment) {
            $storage = $storages->get($storageId);

            if (!$storage) {
                throw ValidationException::withMessages([
                    'stock' => 'Uno de los movimientos no tiene un almacén asociado.',
                ]);
            }

            $newQuantity = (float) $storage->quantity + $adjustment;

            if ($newQuantity < 0) {
                $productName = optional($movements->firstWhere('storage_id', $storageId)->product)->name
                    ?? 'producto desconocido';

                throw ValidationException::withMessages([
                    'stock' => 'No se puede eliminar: el stock del producto "' . $productName . '" quedaría en '
                        . $newQuantity . '. Stock actual: ' . $storage->quantity
                        . ', ajuste requerido: ' . $adjustment . '.',
                ]);
            }

            $storage->update(['quantity' => $newQuantity]);
        }

        StockMovement::whereIn('id', $movements->pluck('id'))->delete();
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
