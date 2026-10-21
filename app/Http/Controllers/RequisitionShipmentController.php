<?php

namespace App\Http\Controllers;

use App\Models\PurchaseItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionShipment;
use App\Models\RequisitionShipmentEvent;
use App\Models\StockMovement;
use App\Notifications\RequisitionArrivalScheduledNotification;
use App\Notifications\RequisitionShipmentStatusNotification;
use App\Support\RequisitionPermissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RequisitionShipmentController extends Controller
{
    /**
     * Alta de una guía de paquetería con los productos (partidas) que viajan en ella.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:100', Rule::unique('requisition_shipments', 'tracking_number')],
            'carrier' => ['nullable', 'string', 'max:100'],
            'delivery_method' => ['nullable', Rule::in(RequisitionShipment::DELIVERY_METHODS)],
            'status' => ['required', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            'arrived_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['exists:requisition_items,id'],
            // Parcialidades: cantidad de cada producto que viaja en la guía.
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:requisition_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ], [
            'tracking_number.unique' => 'Ya existe una guía con ese número; búscala y liga los productos en lugar de duplicarla.',
        ]);

        $deliveryMethod = $request->input('delivery_method') ?: RequisitionShipment::DELIVERY_METHODS[0];

        if ($deliveryMethod === 'Guía' && ! $request->filled('tracking_number')) {
            throw ValidationException::withMessages([
                'tracking_number' => 'Captura el número de guía o elige la entrega por proveedor / transporte propio.',
            ]);
        }

        $entries = $this->shipmentEntries($request);

        $this->validateQuantities($entries);

        $shipment = DB::transaction(function () use ($request, $user, $entries) {
            $shipment = RequisitionShipment::create([
                'tracking_number' => $deliveryMethod === 'Guía' ? $request->tracking_number : null,
                'carrier' => $request->carrier,
                'delivery_method' => $deliveryMethod,
                'status' => $request->status,
                'estimated_arrival_date' => $request->estimated_arrival_date,
                'arrived_at' => $request->status === 'Arribado'
                    ? ($request->filled('arrived_at')
                        ? Carbon::parse($request->arrived_at)->startOfDay()
                        : now()->startOfDay())
                    : null,
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            $shipment->syncItems($entries);

            // Si la guía se registra ya arribada, el inventario entra de inmediato.
            if ($shipment->status === 'Arribado') {
                $this->registerArrivalStock($shipment);
            }

            return $shipment;
        });

        $this->syncItemsStatus($shipment->items()->get(), $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        return back()->with('success', "Entrega {$shipment->display_name} registrada.");
    }

    /**
     * Liga uno o varios productos a una guía existente (o crea la guía si no existe).
     * Un mismo número de guía se reutiliza para todas las requisiciones que agrupa.
     */
    public function assign(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $request->validate([
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['exists:requisition_items,id'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:requisition_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'shipment_id' => ['nullable', 'exists:requisition_shipments,id'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'delivery_method' => ['nullable', Rule::in(RequisitionShipment::DELIVERY_METHODS)],
            'status' => ['nullable', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            'arrived_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $deliveryMethod = $request->input('delivery_method') ?: RequisitionShipment::DELIVERY_METHODS[0];
        $usesGuide = $deliveryMethod === 'Guía';

        if (! $request->filled('shipment_id') && $usesGuide && ! $request->filled('tracking_number')) {
            return back()->withErrors([
                'tracking_number' => 'Selecciona una guía existente o captura el número de guía (o elige proveedor / transporte propio).',
            ]);
        }

        if (empty($request->input('item_ids', [])) && empty($request->input('items', []))) {
            return back()->withErrors(['items' => 'Selecciona al menos un producto de la requisición.']);
        }

        $shipmentId = $request->filled('shipment_id') ? (int) $request->shipment_id : null;
        $existingShipment = $shipmentId
            ? RequisitionShipment::find($shipmentId)
            : ($request->filled('tracking_number')
                ? RequisitionShipment::where('tracking_number', $request->tracking_number)->first()
                : null);
        $entries = $this->shipmentEntries($request);

        // Solo se pueden ligar partidas de la requisición consultada.
        $itemIds = collect($entries)->pluck('id')->all();
        $items = RequisitionItem::where('requisition_id', $requisition->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['items' => 'Selecciona al menos un producto de la requisición.']);
        }

        $this->validateQuantities($entries, $existingShipment, $requisition->id);

        $shipment = DB::transaction(function () use ($request, $user, $entries, $existingShipment, $deliveryMethod) {
            if ($existingShipment) {
                $shipment = $existingShipment;

                // La nota de la entrega alimenta el registro del calendario de arribos
                // y la fecha tentativa capturada se refleja en el mismo calendario.
                $shipment->fill([
                    'notes' => $request->filled('notes') ? $request->notes : $shipment->notes,
                    'carrier' => $request->filled('carrier') ? $request->carrier : $shipment->carrier,
                    'estimated_arrival_date' => $request->filled('estimated_arrival_date')
                        ? $request->estimated_arrival_date
                        : $shipment->estimated_arrival_date,
                    'delivery_method' => $request->filled('delivery_method')
                        ? $deliveryMethod
                        : $shipment->delivery_method,
                ])->save();
            } elseif ($deliveryMethod === 'Guía') {
                // Si el número de guía ya existe se reutiliza la misma guía.
                $shipment = RequisitionShipment::firstOrCreate(
                    ['tracking_number' => $request->tracking_number],
                    [
                        'carrier' => $request->carrier,
                        'delivery_method' => $deliveryMethod,
                        'status' => $request->status ?? 'Pendiente',
                        'estimated_arrival_date' => $request->estimated_arrival_date,
                        'arrived_at' => $request->status === 'Arribado'
                            ? ($request->filled('arrived_at')
                                ? Carbon::parse($request->arrived_at)->startOfDay()
                                : now()->startOfDay())
                            : null,
                        'notes' => $request->notes,
                        'created_by' => $user->id,
                    ]
                );

                // Si la guía ya existía, se conserva pero se actualiza la fecha
                // tentativa capturada (se refleja en el calendario de arribos).
                if (! $shipment->wasRecentlyCreated && $request->filled('estimated_arrival_date')) {
                    $shipment->update(['estimated_arrival_date' => $request->estimated_arrival_date]);
                }
            } else {
                // Entregas sin guía: proveedor o transporte propio de la empresa.
                $shipment = RequisitionShipment::create([
                    'tracking_number' => null,
                    'carrier' => $request->carrier,
                    'delivery_method' => $deliveryMethod,
                    'status' => $request->status ?? 'Pendiente',
                    'estimated_arrival_date' => $request->estimated_arrival_date,
                    'arrived_at' => $request->status === 'Arribado'
                        ? ($request->filled('arrived_at')
                            ? Carbon::parse($request->arrived_at)->startOfDay()
                            : now()->startOfDay())
                        : null,
                    'notes' => $request->notes,
                    'created_by' => $user->id,
                ]);
            }

            // Se conservan las cantidades ya asignadas y se actualizan las enviadas.
            $merged = $shipment->items()
                ->get(['requisition_items.id'])
                ->map(fn (RequisitionItem $item) => ['id' => $item->id, 'quantity' => $item->pivot->quantity])
                ->keyBy('id');

            foreach ($entries as $entry) {
                $merged->put($entry['id'], [
                    'id' => $entry['id'],
                    'quantity' => $entry['quantity'] ?? ($merged[$entry['id']]['quantity'] ?? null),
                ]);
            }

            $shipment->syncItems($merged->values()->all());

            // Guía nueva registrada ya como arribada: entra el inventario.
            if ($shipment->wasRecentlyCreated && $shipment->status === 'Arribado') {
                $this->registerArrivalStock($shipment);
            }

            return $shipment;
        });

        $this->syncItemsStatus($items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        $count = $items->count();

        return back()->with('success', $count === 1
            ? "Producto ligado a la entrega {$shipment->display_name}."
            : "{$count} productos ligados a la entrega {$shipment->display_name}.");
    }

    /**
     * Actualiza la guía y los productos que viajan en ella.
     */
    public function update(Request $request, RequisitionShipment $shipment)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:100', Rule::unique('requisition_shipments', 'tracking_number')->ignore($shipment->id)],
            'carrier' => ['nullable', 'string', 'max:100'],
            'delivery_method' => ['nullable', Rule::in(RequisitionShipment::DELIVERY_METHODS)],
            'status' => ['required', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            'arrived_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['exists:requisition_items,id'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:requisition_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

        $previousStatus = $shipment->status;

        // Una vez marcada como arribada, la guía queda cerrada.
        if ($previousStatus === 'Arribado' && $request->status !== 'Arribado') {
            return back()->withErrors(['status' => 'La guía ya fue marcada como arribada y su estatus no puede cambiarse.']);
        }

        $deliveryMethod = $request->filled('delivery_method')
            ? $request->input('delivery_method')
            : ($shipment->delivery_method ?: RequisitionShipment::DELIVERY_METHODS[0]);

        if ($deliveryMethod === 'Guía' && ! $request->filled('tracking_number')) {
            return back()->withErrors([
                'tracking_number' => 'Captura el número de guía o cambia la forma de entrega a proveedor / transporte propio.',
            ]);
        }

        // Partidas que se desligan al guardar (para recalcular su estatus).
        $removedItems = collect();

        // Se conserva la cantidad ya asignada a cada partida que permanece en la guía.
        $currentQuantities = $shipment->items()->get(['requisition_items.id'])
            ->mapWithKeys(fn (RequisitionItem $item) => [$item->id => $item->pivot->quantity]);

        $hasItemUpdate = $request->has('item_ids') || $request->has('items');
        $entries = $this->shipmentEntries($request);

        if ($hasItemUpdate) {
            $entries = collect($entries)->map(fn (array $entry) => [
                'id' => $entry['id'],
                'quantity' => $entry['quantity'] ?? ($currentQuantities[$entry['id']] ?? null),
            ])->values()->all();

            $this->validateQuantities($entries, $shipment);
        }

        DB::transaction(function () use ($request, $shipment, $previousStatus, $deliveryMethod, &$removedItems, $hasItemUpdate, $entries) {
            if ($hasItemUpdate) {
                $removedItems = $shipment->items()
                    ->whereNotIn('requisition_items.id', collect($entries)->pluck('id')->all())
                    ->get();
            }

            $shipment->update([
                'tracking_number' => $deliveryMethod === 'Guía' ? $request->tracking_number : null,
                'carrier' => $request->carrier,
                'delivery_method' => $deliveryMethod,
                'status' => $request->status,
                'estimated_arrival_date' => $request->estimated_arrival_date,
                'arrived_at' => $request->status === 'Arribado'
                    ? ($request->filled('arrived_at')
                        ? Carbon::parse($request->arrived_at)->startOfDay()
                        : ($shipment->arrived_at ?? now()->startOfDay()))
                    : null,
                'notes' => $request->notes,
            ]);

            if ($hasItemUpdate) {
                $shipment->syncItems($entries);
            }

            if ($request->status === 'Arribado' && $previousStatus !== 'Arribado') {
                $this->registerArrivalStock($shipment);
            }
        });

        foreach ($removedItems as $item) {
            $this->refreshItemArrivalStatus($item);
        }

        $shipment->load('items');
        $this->syncItemsStatus($shipment->items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        return back()->with('success', 'Guía actualizada.');
    }

    /**
     * Cambio de estatus de la guía (actualiza partidas, requisiciones y notifica).
     */
    public function updateStatus(Request $request, RequisitionShipment $shipment)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $request->validate([
            'status' => ['required', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            // Fecha real de arribo: editable, por defecto el día del cambio de estatus.
            'arrived_at' => ['nullable', 'date'],
            // Descripción de la actualización: se guarda como evento con su fecha
            // para conservar el histórico del seguimiento.
            'event_date' => ['nullable', 'date'],
            'event_description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = $shipment->status;

        // Una vez marcada como arribada, la guía queda cerrada.
        if ($previousStatus === 'Arribado' && $request->status !== 'Arribado') {
            return back()->withErrors(['status' => 'La guía ya fue marcada como arribada y su estatus no puede cambiarse.']);
        }

        $arrivedAt = $request->status === 'Arribado'
            ? ($request->filled('arrived_at')
                ? Carbon::parse($request->arrived_at)->startOfDay()
                : ($shipment->arrived_at ?? now()->startOfDay()))
            : null;

        DB::transaction(function () use ($request, $shipment, $arrivedAt, $previousStatus, $user) {
            $shipment->update([
                'status' => $request->status,
                'estimated_arrival_date' => $request->input('estimated_arrival_date', $shipment->estimated_arrival_date),
                'arrived_at' => $arrivedAt,
                'notes' => $request->has('notes') ? $request->notes : $shipment->notes,
            ]);

            // Cada descripción capturada en el calendario queda como una
            // actualización independiente, con su propia fecha y descripción.
            if ($request->filled('event_description')) {
                $shipment->events()->create([
                    'event_date' => $request->filled('event_date')
                        ? $request->event_date
                        : now()->toDateString(),
                    'description' => $request->event_description,
                    'created_by' => $user->id,
                ]);
            }

            // El primer cambio a "Arribado" registra la entrada al inventario y
            // sincroniza las piezas recibidas de la orden de compra vinculada.
            if ($request->status === 'Arribado' && $previousStatus !== 'Arribado') {
                $this->registerArrivalStock($shipment);
            }
        });

        $shipment->load('items');
        $this->syncItemsStatus($shipment->items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        return back()->with('success', "Entrega {$shipment->display_name}: {$shipment->status}.");
    }

    /**
     * Elimina una guía y desliga sus productos.
     */
    public function destroy(RequisitionShipment $shipment)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $reference = $shipment->display_name;

        DB::transaction(function () use ($shipment) {
            $requisitions = collect();
            $items = $shipment->items()->get();

            foreach ($items as $item) {
                if ($item->requisition) {
                    $requisitions->push($item->requisition);
                }
            }

            $shipment->items()->detach();
            $shipment->delete();

            foreach ($items as $item) {
                $this->refreshItemArrivalStatus($item);
            }

            foreach ($requisitions->unique('id') as $requisition) {
                $requisition->refreshArrivalStatusFromItems();
            }
        });

        return back()->with('success', "Entrega {$reference} eliminada.");
    }

    /**
     * Registra una actualización / evento con fecha propia sobre una guía.
     */
    public function storeEvent(Request $request, RequisitionShipment $shipment)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $validated = $request->validate([
            'event_date' => ['required', 'date'],
            // Ubicación / etapa: China, GDL, Aduanas u otra capturada libremente.
            'location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000', 'required_without:location'],
            // La actualización también puede mover el estatus y la fecha tentativa.
            'status' => ['nullable', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            'arrived_at' => ['nullable', 'date'],
        ], [
            'description.required_without' => 'Selecciona una ubicación o escribe una descripción.',
        ]);

        $previousStatus = $shipment->status;
        $status = $validated['status'] ?? null;

        // Una guía arribada queda cerrada: no admite cambios de estatus.
        if ($previousStatus === 'Arribado' && $status && $status !== 'Arribado') {
            return back()->withErrors(['status' => 'La guía ya fue marcada como arribada; su estatus no puede cambiarse.']);
        }

        DB::transaction(function () use ($request, $validated, $shipment, $user, $status, $previousStatus) {
            $shipment->events()->create([
                'event_date' => $validated['event_date'],
                'location' => $validated['location'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $user->id,
            ]);

            $changes = [];

            if ($status) {
                $changes['status'] = $status;

                if ($status === 'Arribado') {
                    $changes['arrived_at'] = ! empty($validated['arrived_at'])
                        ? Carbon::parse($validated['arrived_at'])->startOfDay()
                        : ($shipment->arrived_at ?? Carbon::parse($validated['event_date'])->startOfDay());
                } else {
                    $changes['arrived_at'] = null;
                }
            }

            if ($request->has('estimated_arrival_date')) {
                $changes['estimated_arrival_date'] = $validated['estimated_arrival_date'] ?? null;
            }

            if (! empty($changes)) {
                $shipment->update($changes);
            }

            // El primer cambio a "Arribado" registra la entrada al inventario.
            if ($status === 'Arribado' && $previousStatus !== 'Arribado') {
                $this->registerArrivalStock($shipment);
            }
        });

        if ($status && $status !== $previousStatus) {
            $shipment->load('items');
            $this->syncItemsStatus($shipment->items, $shipment);
            $shipment->load('items.requisition', 'requisitions');
            $this->notifyShipment($shipment, $user);
        }

        // Solo avisa si la fecha tentativa cambió con la actualización.
        $this->notifyArrivalDate($shipment, $user);

        return back()->with('success', "Actualización registrada en la entrega {$shipment->display_name}.");
    }

    /**
     * Elimina una actualización / evento del calendario de arribos.
     */
    public function destroyEvent(RequisitionShipmentEvent $event)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $reference = $event->shipment?->display_name;
        $event->delete();

        return back()->with('success', $reference
            ? "Actualización de la entrega {$reference} eliminada."
            : 'Actualización eliminada.');
    }

    /**
     * Registra la entrada al inventario de la mercancía que arribó en la guía y
     * sincroniza las piezas recibidas de la orden de compra de la requisición.
     * Con parcialidades, cada guía suma su propia cantidad.
     */
    private function registerArrivalStock(RequisitionShipment $shipment): void
    {
        // Se fuerza la recarga para tomar las partidas y cantidades vigentes.
        $shipment->load(['items.product', 'items.requisition']);

        foreach ($shipment->items as $item) {
            $product = $item->product;

            // Los productos nuevos (sin catálogo) no afectan el inventario.
            if (! $product) {
                continue;
            }

            // Cantidad que viaja en esta guía (puede ser una parcialidad).
            $quantity = $item->pivot->quantity !== null
                ? (float) $item->pivot->quantity
                : (float) $item->quantity;

            if ($quantity <= 0) {
                continue;
            }

            // 1. Se sincronizan las piezas recibidas de las órdenes de compra
            //    vinculadas a la requisición, sin rebasar lo comprado.
            $purchaseItems = PurchaseItem::query()
                ->where('product_id', $product->id)
                ->whereHas('purchase', fn ($query) => $query->where('requisition_id', $item->requisition_id))
                ->get();

            $pending = $quantity;

            foreach ($purchaseItems as $purchaseItem) {
                if ($pending <= 0) {
                    break;
                }

                $missing = (float) $purchaseItem->quantity - (float) ($purchaseItem->quantity_received ?? 0);

                if ($missing <= 0) {
                    continue;
                }

                $received = min($pending, $missing);
                $purchaseItem->update([
                    'quantity_received' => (float) ($purchaseItem->quantity_received ?? 0) + $received,
                ]);
                $pending -= $received;
            }

            // 2. Movimiento de entrada al inventario por la mercancía recibida.
            $storage = $product->storages()->firstOrCreate([], ['quantity' => 0]);
            $storage->increment('quantity', $quantity);

            StockMovement::create([
                'product_id' => $product->id,
                'storage_id' => $storage->id,
                'quantity_change' => $quantity,
                'type' => 'Entrada',
                'notes' => 'Entrada por arribo de entrega ' . $shipment->display_name
                    . ($item->requisition?->folio ? ' · ' . $item->requisition->folio : ''),
            ]);
        }
    }

    /**
     * Sincroniza el estatus de arribo de las partidas de la guía y, en el flujo
     * de importación, avanza la etapa de la requisición.
     */
    private function syncItemsStatus($items, RequisitionShipment $shipment): void
    {
        $requisitions = collect();

        foreach ($items as $item) {
            $this->refreshItemArrivalStatus($item);

            if ($item->requisition) {
                $requisitions->push($item->requisition);
            }
        }

        foreach ($requisitions->unique('id') as $requisition) {
            $requisition->refreshArrivalStatusFromItems();
            $requisition->refresh();

            if ($shipment->status === 'En tránsito' && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica', 'Documentación'], true)) {
                $requisition->update(['status' => 'En tránsito']);
            }

            // Con parcialidades, la requisición solo se da por recibida cuando
            // todas sus partidas llegaron completas.
            $fullyArrived = ! $requisition->items()->where('arrival_status', '!=', 'Arribado')->exists();

            if ($shipment->status === 'Arribado'
                && $requisition->type === Requisition::TYPE_IMPORT
                && $fullyArrived
                && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica', 'Documentación', 'En tránsito'], true)) {
                $requisition->update(['status' => 'Arribo confirmado']);
            }
        }
    }

    /**
     * Recalcula el estatus de una partida a partir de las guías (y cantidades)
     * en las que viaja: con parcialidades no se marca "Arribado" hasta que se
     * recibe la cantidad total solicitada.
     */
    private function refreshItemArrivalStatus(RequisitionItem $item): void
    {
        $total = (float) $item->quantity;
        $assigned = 0.0;
        $arrived = 0.0;
        $inTransit = false;
        $incident = false;

        foreach ($item->shipments()->get() as $shipment) {
            $quantity = $shipment->pivot->quantity !== null
                ? (float) $shipment->pivot->quantity
                : $total;

            $assigned += $quantity;

            if ($shipment->status === 'Arribado') {
                $arrived += $quantity;
            }

            if ($shipment->status === 'En tránsito') {
                $inTransit = true;
            }

            if ($shipment->status === 'Incidencia') {
                $incident = true;
            }
        }

        $status = match (true) {
            $incident => 'Incidencia',
            $assigned + 0.001 >= $total && $arrived + 0.001 >= $total => 'Arribado',
            $arrived > 0 || $inTransit => 'En tránsito',
            default => 'Pendiente',
        };

        if ($item->arrival_status !== $status) {
            $item->update(['arrival_status' => $status]);
        }
    }

    /**
     * Normaliza las partidas enviadas a la guía como [{id, quantity}].
     *
     * @return array<int, array{id: int, quantity: float|null}>
     */
    private function shipmentEntries(Request $request): array
    {
        $entries = collect($request->input('items', []))
            ->map(fn (array $entry) => [
                'id' => (int) $entry['id'],
                'quantity' => isset($entry['quantity']) && $entry['quantity'] !== ''
                    ? (float) $entry['quantity']
                    : null,
            ]);

        // Compatibilidad con el flujo anterior basado solo en item_ids.
        if ($entries->isEmpty()) {
            $entries = collect($request->input('item_ids', []))
                ->map(fn ($id) => ['id' => (int) $id, 'quantity' => null]);
        }

        return $entries
            ->filter(fn (array $entry) => $entry['quantity'] === null || $entry['quantity'] > 0)
            ->unique('id')
            ->values()
            ->all();
    }

    /**
     * Valida que las cantidades asignadas no excedan lo solicitado en cada partida.
     *
     * @param  array<int, array{id: int, quantity: float|null}>  $entries
     */
    private function validateQuantities(array $entries, ?RequisitionShipment $shipment = null, ?int $requisitionId = null): void
    {
        if (empty($entries)) {
            return;
        }

        $items = RequisitionItem::whereIn('id', collect($entries)->pluck('id'))
            ->when($requisitionId, fn ($query) => $query->where('requisition_id', $requisitionId))
            ->get()
            ->keyBy('id');

        foreach ($entries as $entry) {
            $item = $items[$entry['id']] ?? null;

            if (! $item) {
                continue;
            }

            $assignedElsewhere = (float) DB::table('requisition_item_shipment')
                ->where('requisition_item_id', $item->id)
                ->when($shipment, fn ($query) => $query->where('requisition_shipment_id', '!=', $shipment->id))
                ->sum('quantity');

            $requested = $entry['quantity'] ?? max(0, round((float) $item->quantity - $assignedElsewhere, 2));

            if ($assignedElsewhere + $requested > (float) $item->quantity + 0.001) {
                $available = max(0, round((float) $item->quantity - $assignedElsewhere, 2));

                throw ValidationException::withMessages([
                    'items' => "La cantidad de «{$this->itemLabel($item)}» excede lo solicitado. Disponible para asignar: {$available}.",
                ]);
            }
        }
    }

    private function itemLabel(RequisitionItem $item): string
    {
        return $item->product?->name ?? $item->description ?? "Partida #{$item->id}";
    }

    private function notifyShipment(RequisitionShipment $shipment, $author): void
    {
        $recipients = RequisitionPermissions::usersToNotify(
            array_merge(
                RequisitionPermissions::EDIT,
                RequisitionPermissions::AUTHORIZE,
                RequisitionPermissions::MANAGE_ARRIVALS,
            ),
            $author
        );

        // Se notifica también a quienes levantaron las requisiciones involucradas.
        foreach ($shipment->requisitions as $requisition) {
            if ($requisition->user && $requisition->user->id !== $author->id) {
                $recipients->push($requisition->user);
            }
        }

        $recipients = $recipients->unique('id')->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new RequisitionShipmentStatusNotification($shipment));
        }
    }

    /**
     * Avisa a almacén, jefe de producción y Samuel cuando se registra o cambia
     * la fecha tentativa de arribo; el enlace abre el calendario de arribos en
     * la guía correspondiente.
     */
    private function notifyArrivalDate(RequisitionShipment $shipment, $author): void
    {
        if (! $shipment->estimated_arrival_date) {
            return;
        }

        // En altas recientes no hay "cambios" que comparar: basta con que la fecha exista.
        if (! $shipment->wasRecentlyCreated && ! $shipment->wasChanged('estimated_arrival_date')) {
            return;
        }

        $recipients = RequisitionPermissions::arrivalTrackingUsers($author);

        if ($recipients->isEmpty()) {
            return;
        }

        $shipment->loadMissing(['requisitions', 'items.requisition']);

        Notification::send($recipients, new RequisitionArrivalScheduledNotification($shipment));
    }
}
