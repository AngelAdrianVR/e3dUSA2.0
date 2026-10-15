<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionShipment;
use App\Models\RequisitionShipmentEvent;
use App\Notifications\RequisitionShipmentStatusNotification;
use App\Support\RequisitionPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

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
        ], [
            'tracking_number.unique' => 'Ya existe una guía con ese número; búscala y liga los productos en lugar de duplicarla.',
        ]);

        $shipment = DB::transaction(function () use ($request, $user) {
            $shipment = RequisitionShipment::create([
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
                'status' => $request->status,
                'estimated_arrival_date' => $request->estimated_arrival_date,
                'arrived_at' => $request->status === 'Arribado' ? now() : null,
                'notes' => $request->notes,
                'created_by' => $user->id,
            ]);

            $shipment->syncItems($request->item_ids ?? []);

            return $shipment;
        });

        $this->syncItemsStatus($shipment->items()->get(), $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);

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
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['exists:requisition_items,id'],
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

        // Solo se pueden ligar partidas de la requisición consultada.
        $items = RequisitionItem::where('requisition_id', $requisition->id)
            ->whereIn('id', $request->item_ids)
            ->get();

        if ($items->isEmpty()) {
            return back()->withErrors(['item_ids' => 'Selecciona al menos un producto de la requisición.']);
        }

        $shipment = DB::transaction(function () use ($request, $items, $user) {
            if ($request->filled('shipment_id')) {
                $shipment = RequisitionShipment::findOrFail($request->shipment_id);
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

            $itemIds = $shipment->items()->pluck('requisition_items.id')->merge($items->pluck('id'))->unique()->all();
            $shipment->syncItems($itemIds);

            return $shipment;
        });

        $this->syncItemsStatus($items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);

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
        ]);

        // Partidas que se desligan al guardar (para recalcular su estatus).
        $removedItems = collect();

        DB::transaction(function () use ($request, $shipment, &$removedItems) {
            if ($request->has('item_ids')) {
                $removedItems = $shipment->items()
                    ->whereNotIn('requisition_items.id', $request->item_ids ?? [])
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

            if ($request->has('item_ids')) {
                $shipment->syncItems($request->item_ids ?? []);
            }
        });

        foreach ($removedItems as $item) {
            $item->update(['arrival_status' => 'Pendiente']);
            $item->requisition?->refreshArrivalStatusFromItems();
        }

        $shipment->load('items');
        $this->syncItemsStatus($shipment->items, $shipment);
        $shipment->load(['items.requisition', 'requisitions']);
        $this->notifyShipment($shipment, $user);

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

            foreach ($shipment->items as $item) {
                if ($item->arrival_status !== 'Arribado') {
                    $item->update(['arrival_status' => 'Pendiente']);
                }

                if ($item->requisition) {
                    $requisitions->push($item->requisition);
                }
            }

            $shipment->items()->detach();
            $shipment->delete();

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
            $item->arrival_status = $shipment->status;
            $item->save();

            $requisition = $item->requisition;

            if (! $requisition) {
                continue;
            }

            if ($shipment->status === 'En tránsito' && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica'], true)) {
                $requisition->status = 'En tránsito';
                $requisition->save();
            }

            if ($shipment->status === 'Arribado'
                && $requisition->type === Requisition::TYPE_IMPORT
                && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica', 'En tránsito'], true)) {
                $requisition->status = 'Arribo confirmado';
                $requisition->save();
            }

            $requisitions->push($requisition);
        }

        foreach ($requisitions->unique('id') as $requisition) {
            $requisition->refreshArrivalStatusFromItems();
        }
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
}
