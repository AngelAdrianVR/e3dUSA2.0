<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionShipment;
use App\Models\RequisitionShipmentEvent;
use App\Notifications\RequisitionArrivalScheduledNotification;
use App\Notifications\RequisitionShipmentStatusNotification;
use App\Support\RequisitionPermissions;
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
            'tracking_number' => ['required', 'string', 'max:100', Rule::unique('requisition_shipments', 'tracking_number')],
            'carrier' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
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

        $entries = $this->shipmentEntries($request);

        $this->validateQuantities($entries);

        $shipment = DB::transaction(function () use ($request, $user, $entries) {
            $shipment = RequisitionShipment::create([
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'status' => $request->status,
                'estimated_arrival_date' => $request->estimated_arrival_date,
                'arrived_at' => $request->status === 'Arribado' ? now() : null,
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            $shipment->syncItems($entries);

            return $shipment;
        });

        $this->syncItemsStatus($shipment->items()->get(), $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        return back()->with('success', "Guía {$shipment->tracking_number} registrada.");
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
            'status' => ['nullable', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $request->filled('shipment_id') && ! $request->filled('tracking_number')) {
            return back()->withErrors(['tracking_number' => 'Selecciona una guía existente o captura el número de guía.']);
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

        $shipment = DB::transaction(function () use ($request, $user, $entries, $existingShipment, $items) {
            if ($existingShipment) {
                $shipment = $existingShipment;
            } else {
                // Si el número de guía ya existe se reutiliza la misma guía.
                $shipment = RequisitionShipment::firstOrCreate(
                    ['tracking_number' => $request->tracking_number],
                    [
                        'carrier' => $request->carrier,
                        'status' => $request->status ?? 'Pendiente',
                        'estimated_arrival_date' => $request->estimated_arrival_date,
                        'arrived_at' => $request->status === 'Arribado' ? now() : null,
                        'notes' => $request->notes,
                        'created_by' => $user->id,
                    ]
                );
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

            return $shipment;
        });

        $this->syncItemsStatus($items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        $count = $items->count();

        return back()->with('success', $count === 1
            ? "Producto ligado a la guía {$shipment->tracking_number}."
            : "{$count} productos ligados a la guía {$shipment->tracking_number}.");
    }

    /**
     * Actualiza la guía y los productos que viajan en ella.
     */
    public function update(Request $request, RequisitionShipment $shipment)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $request->validate([
            'tracking_number' => ['required', 'string', 'max:100', Rule::unique('requisition_shipments', 'tracking_number')->ignore($shipment->id)],
            'carrier' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(RequisitionShipment::STATUSES)],
            'estimated_arrival_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['exists:requisition_items,id'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:requisition_items,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ]);

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

        DB::transaction(function () use ($request, $shipment, &$removedItems, $hasItemUpdate, $entries) {
            if ($hasItemUpdate) {
                $removedItems = $shipment->items()
                    ->whereNotIn('requisition_items.id', collect($entries)->pluck('id')->all())
                    ->get();
            }

            $shipment->update([
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'status' => $request->status,
                'estimated_arrival_date' => $request->estimated_arrival_date,
                'arrived_at' => $request->status === 'Arribado' ? ($shipment->arrived_at ?? now()) : null,
                'notes' => $request->notes,
            ]);

            if ($hasItemUpdate) {
                $shipment->syncItems($entries);
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
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $shipment->update([
            'status' => $request->status,
            'estimated_arrival_date' => $request->input('estimated_arrival_date', $shipment->estimated_arrival_date),
            'arrived_at' => $request->status === 'Arribado' ? ($shipment->arrived_at ?? now()) : null,
            'notes' => $request->has('notes') ? $request->notes : $shipment->notes,
        ]);

        $shipment->load('items');
        $this->syncItemsStatus($shipment->items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);
        $this->notifyArrivalDate($shipment, $user);

        return back()->with('success', "Guía {$shipment->tracking_number}: {$shipment->status}.");
    }

    /**
     * Elimina una guía y desliga sus productos.
     */
    public function destroy(RequisitionShipment $shipment)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $tracking = $shipment->tracking_number;

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

        return back()->with('success', "Guía {$tracking} eliminada.");
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
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $shipment->events()->create([
            'event_date' => $validated['event_date'],
            'description' => $validated['description'],
            'created_by' => $user->id,
        ]);

        return back()->with('success', "Actualización registrada en la guía {$shipment->tracking_number}.");
    }

    /**
     * Elimina una actualización / evento del calendario de arribos.
     */
    public function destroyEvent(RequisitionShipmentEvent $event)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $tracking = $event->shipment?->tracking_number;
        $event->delete();

        return back()->with('success', $tracking
            ? "Actualización de la guía {$tracking} eliminada."
            : 'Actualización eliminada.');
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

            if ($shipment->status === 'En tránsito' && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica'], true)) {
                $requisition->update(['status' => 'En tránsito']);
            }

            // Con parcialidades, la requisición solo se da por recibida cuando
            // todas sus partidas llegaron completas.
            $fullyArrived = ! $requisition->items()->where('arrival_status', '!=', 'Arribado')->exists();

            if ($shipment->status === 'Arribado'
                && $requisition->type === Requisition::TYPE_IMPORT
                && $fullyArrived
                && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica', 'En tránsito'], true)) {
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
