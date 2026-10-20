<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\Event;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionShipment;
use App\Models\RequisitionShipmentEvent;
use App\Models\Task;
use App\Models\User;
use App\Notifications\EventInvitationNotification;
use App\Notifications\EventResponseNotification;
use App\Support\RequisitionPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarController extends Controller
{
    /**
     * Días máximos que se repite el seguimiento de una guía en el calendario.
     */
    private const CARRY_DAYS_LIMIT = 120;

    public function index()
    {
        $authUserId = auth()->id();

        // 1. OBTENER INVITACIONES PENDIENTES
        // Buscamos eventos donde el usuario actual es un participante
        // y su estado en la tabla pivote es 'Pendiente'.
        $pendingInvitations = Event::whereHas('participants', function ($query) use ($authUserId) {
            $query->where('user_id', $authUserId)->where('status', 'Pendiente');
        })
        ->with('calendarEntry:id,title,start_datetime,entryable_id,entryable_type') // Cargamos datos del evento
        ->get();


        // 2. OBTENER ENTRADAS PARA MOSTRAR EN EL CALENDARIO
        $calendarEntries = CalendarEntry::query()
            // A) Entradas creadas por el usuario actual (Eventos o Tareas)
            ->where('user_id', $authUserId)
            // B) O eventos a los que el usuario fue invitado y ACEPTÓ
            ->orWhereHasMorph('entryable', [Event::class], function ($query) use ($authUserId) {
                $query->whereHas('participants', function ($subQuery) use ($authUserId) {
                    $subQuery->where('user_id', $authUserId)->where('status', 'Aceptado');
                });
            })
            // Cargamos las relaciones de forma eficiente
            ->with(['entryable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Event::class => ['participants:id,name'],
                    Task::class => [],
                ]);
            }])
            ->get();

        return Inertia::render('Calendar/Index', [
            'calendarEntries' => $calendarEntries,
            'pendingInvitations' => $pendingInvitations, // Pasamos las invitaciones a la vista
            'authUserId' => $authUserId,
            'requisitionArrivals' => $this->requisitionArrivals(),
        ]);
    }
    /**
     * Arribos de mercancía para la pestaña del calendario.
     *
     * Cada guía de paquetería genera UNA sola entrada (agrupa todos los productos
     * que viajan en ella). Los productos con fecha estimada que aún no tienen guía
     * se muestran como entradas individuales.
     */
    private function requisitionArrivals(): array
    {
        $user = auth()->user();
        $canManage = RequisitionPermissions::canManageArrivals($user);
        $canViewDetails = RequisitionPermissions::canViewDetails($user);

        $shipments = RequisitionShipment::with([
            'items:id,requisition_id,product_id,quantity,arrival_status',
            'items.product:id,name,code',
            'items.product.media',
            'items.requisition:id,type,status,branch_id,business_name,product_id,arrival_status,user_id',
            'items.requisition.user:id,name',
            'items.requisition.branch:id,name',
            'requisitions:id,type,status,branch_id,business_name,user_id',
            'requisitions.user:id,name',
            'requisitions.branch:id,name',
            'events:id,requisition_shipment_id,event_date,location,description,created_by',
            'events.creator:id,name',
        ])->orderBy('created_at', 'desc')->get();

        $arrivals = [];
        $undated = [];

        foreach ($shipments as $shipment) {
            // Detalle compartido entre la guía y cada una de sus actualizaciones.
            $items = $shipment->items->map(fn (RequisitionItem $item) => [
                'id' => $item->id,
                'product' => $item->product?->name ?? 'Mercancía',
                'code' => $item->product?->code,
                'image_url' => $this->productImageUrl($item->product),
                'requisition_id' => $item->requisition_id,
                // Cantidad que viaja en esta guía (parcialidad) y total solicitado.
                'quantity' => (float) ($item->pivot->quantity ?? $item->quantity),
                'total_quantity' => (float) $item->quantity,
                'status' => $item->arrival_status,
                'color' => $item->arrival_color,
                'folio' => $item->requisition?->folio,
                'requester' => $item->requisition?->user?->name,
                'url' => $canViewDetails && $item->requisition ? route('requisitions.show', $item->requisition->id, false) : null,
            ])->values()->all();

            $requisitions = $shipment->requisitions->map(fn (Requisition $requisition) => [
                'id' => $requisition->id,
                'folio' => $requisition->folio,
                'type' => $requisition->type,
                'status' => $requisition->status,
                'label' => $requisition->branch?->name ?? 'Requisición',
                'creator' => $requisition->user?->name,
                'url' => $canViewDetails ? route('requisitions.show', $requisition->id, false) : null,
            ])->values()->all();

            $events = $shipment->events
                ->sortBy('event_date')
                ->map(fn (RequisitionShipmentEvent $shipmentEvent) => [
                    'id' => $shipmentEvent->id,
                    'date' => $shipmentEvent->event_date?->toDateString(),
                    'location' => $shipmentEvent->location,
                    'description' => $shipmentEvent->description,
                    'label' => $shipmentEvent->label,
                    'created_by_name' => $shipmentEvent->creator?->name,
                ])->values()->all();

            /*
             * Registros base del seguimiento: el alta de la guía (con la nota
             * capturada al asignarla) y cada actualización / evento. La guía se
             * ancla al día en que se asignó, no a la fecha tentativa de arribo.
             */
            $records = collect();

            if ($shipment->created_at) {
                $records->push([
                    'key' => 'shipment-' . $shipment->id,
                    'kind' => 'shipment',
                    'id' => $shipment->id,
                    'date' => $shipment->created_at->toDateString(),
                    'label' => $shipment->notes,
                    'created_by_name' => null,
                ]);
            }

            foreach ($shipment->events as $shipmentEvent) {
                if (! $shipmentEvent->event_date) {
                    continue;
                }

                $records->push([
                    'key' => 'shipment-event-' . $shipmentEvent->id,
                    'kind' => 'event',
                    'id' => $shipmentEvent->id,
                    'date' => $shipmentEvent->event_date->toDateString(),
                    'label' => $shipmentEvent->label,
                    'created_by_name' => $shipmentEvent->creator?->name,
                ]);
            }

            $records = $records->sortBy('date')->values();

            $entry = [
                'id' => $shipment->id,
                'shipment_id' => $shipment->id,
                // Número de guía o forma de entrega (proveedor / transporte propio).
                'title' => $shipment->display_name,
                'tracking_number' => $shipment->tracking_number,
                'delivery_method' => $shipment->delivery_method ?? RequisitionShipment::DELIVERY_METHODS[0],
                'subtitle' => $shipment->carrier ?: ($shipment->delivery_method ?? null),
                'status' => $shipment->status,
                'color' => $shipment->color,
                'estimated_arrival_date' => $shipment->estimated_arrival_date?->toDateString(),
                'arrived_at' => $shipment->arrived_at?->toDateTimeString(),
                'notes' => $shipment->notes,
                'events' => $events,
                'items' => $items,
                'requisitions' => $requisitions,
            ];

            $start = $shipment->created_at?->copy()->startOfDay();

            if (! $start) {
                // Sin fecha de asignación no hay día al que anclar la guía.
                $undated[] = [
                    ...$entry,
                    'key' => 'shipment-' . $shipment->id,
                    'kind' => 'shipment',
                    'date' => null,
                    'description' => $shipment->notes,
                    'state_label' => $shipment->notes,
                    'state_kind' => 'shipment',
                    'state_date' => null,
                    'carried' => false,
                    'created_by_name' => null,
                ];

                continue;
            }

            // Se repite el último estado conocido hasta el arribo (o hasta hoy).
            $today = now()->startOfDay();
            $end = $shipment->arrived_at?->copy()->startOfDay() ?? $today;

            if ($end->gt($today)) {
                $end = $today->copy();
            }

            if ($end->lt($start)) {
                $end = $start->copy();
            }

            // Ventana máxima de repetición para no inflar el calendario.
            $windowStart = $end->copy()->subDays(self::CARRY_DAYS_LIMIT);

            if ($start->lt($windowStart)) {
                $start = $windowStart;
            }

            // Los registros fuera de la ventana de repetición (anteriores a ella o
            // programados a futuro) conservan su propia entrada.
            $outsideRange = $records->filter(fn (array $record) => $record['date'] < $start->toDateString()
                || $record['date'] > $end->toDateString());

            foreach ($outsideRange as $record) {
                $arrivals[] = [
                    ...$entry,
                    'key' => $record['key'],
                    'kind' => $record['kind'],
                    'id' => $record['id'],
                    'date' => $record['date'],
                    'description' => $record['label'],
                    'state_label' => $record['label'],
                    'state_kind' => $record['kind'],
                    'state_date' => $record['date'],
                    'carried' => false,
                    'created_by_name' => $record['created_by_name'],
                ];
            }

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $date = $day->toDateString();
                $dayRecords = $records->filter(fn (array $record) => $record['date'] === $date);

                // Los días con registro propio (alta o actualización) no se repiten.
                foreach ($dayRecords as $record) {
                    $arrivals[] = [
                        ...$entry,
                        'key' => $record['key'],
                        'kind' => $record['kind'],
                        'id' => $record['id'],
                        'date' => $date,
                        'description' => $record['label'],
                        'state_label' => $record['label'],
                        'state_kind' => $record['kind'],
                        'state_date' => $record['date'],
                        'carried' => false,
                        'created_by_name' => $record['created_by_name'],
                    ];
                }

                if ($dayRecords->isNotEmpty()) {
                    continue;
                }

                // Días intermedios: se arrastra el último estado registrado.
                $current = $records->filter(fn (array $record) => $record['date'] <= $date)->last();

                if (! $current) {
                    continue;
                }

                $arrivals[] = [
                    ...$entry,
                    'key' => "shipment-{$shipment->id}-{$date}",
                    'kind' => 'shipment',
                    'id' => $shipment->id,
                    'date' => $date,
                    'description' => $current['label'],
                    'state_label' => $current['label'],
                    'state_kind' => $current['kind'],
                    'state_date' => $current['date'],
                    'carried' => true,
                    'created_by_name' => $current['created_by_name'],
                ];
            }
        }

        // Productos con fecha de entrega que aún no viajan en ninguna guía.
        $standaloneItems = RequisitionItem::with([
            'product:id,name,code',
            'product.media',
            'requisition:id,type,status,branch_id,business_name,expected_delivery_date,arrival_status,user_id',
            'requisition.user:id,name',
        ])
            ->whereDoesntHave('shipments')
            ->whereHas('requisition', fn ($query) => $query
                ->whereNotNull('expected_delivery_date')
                ->whereNotIn('status', ['Completada', 'Rechazada']))
            ->get();

        foreach ($standaloneItems as $item) {
            $requisition = $item->requisition;

            if (! $requisition) {
                continue;
            }

            $arrivals[] = [
                'key' => 'item-' . $item->id,
                'kind' => 'item',
                'id' => $item->id,
                'title' => $requisition->folio,
                'subtitle' => $item->product?->name,
                'status' => $item->arrival_status,
                'color' => $item->arrival_color,
                'date' => $requisition->expected_delivery_date->toDateString(),
                'estimated_arrival_date' => $requisition->expected_delivery_date->toDateString(),
                'arrived_at' => null,
                'notes' => $item->arrival_notes,
                'items' => [[
                    'id' => $item->id,
                    'product' => $item->product?->name ?? 'Mercancía',
                    'code' => $item->product?->code,
                    'image_url' => $this->productImageUrl($item->product),
                    'requisition_id' => $requisition->id,
                    'quantity' => (float) $item->quantity,
                    'total_quantity' => (float) $item->quantity,
                    'status' => $item->arrival_status,
                    'color' => $item->arrival_color,
                    'folio' => $requisition->folio,
                    'requester' => $requisition->user?->name,
                    'url' => $canViewDetails ? route('requisitions.show', $requisition->id, false) : null,
                ]],
                'requisitions' => [[
                    'id' => $requisition->id,
                    'folio' => $requisition->folio,
                    'type' => $requisition->type,
                    'status' => $requisition->status,
                    'label' => $requisition->branch?->name ?? $item->product?->name ?? 'Requisición',
                    'creator' => $requisition->user?->name,
                    'url' => $canViewDetails ? route('requisitions.show', $requisition->id, false) : null,
                ]],
            ];
        }

        return [
            'enabled' => true,
            'can_manage' => $canManage,
            'arrivals' => $arrivals,
            'undated' => $undated,
            // Ubicaciones / etapas sugeridas al registrar actualizaciones.
            'event_locations' => RequisitionShipmentEvent::LOCATIONS,
            // Guías disponibles para registrar actualizaciones / eventos.
            'shipment_options' => $canManage
                ? RequisitionShipment::orderByDesc('created_at')
                    ->get(['id', 'tracking_number', 'carrier', 'delivery_method', 'status', 'estimated_arrival_date'])
                    ->map(fn (RequisitionShipment $shipment) => [
                        'id' => $shipment->id,
                        'tracking_number' => $shipment->tracking_number,
                        'display_name' => $shipment->display_name,
                        'delivery_method' => $shipment->delivery_method,
                        'carrier' => $shipment->carrier,
                        'status' => $shipment->status,
                        'estimated_arrival_date' => $shipment->estimated_arrival_date?->toDateString(),
                    ])
                    ->values()
                    ->all()
                : [],
        ];
    }

    /**
     * Imagen del producto de catálogo (resurtido) para el detalle del arribo.
     */
    private function productImageUrl(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        return $product->getFirstMediaUrl('images')
            ?: ($product->media->first()?->original_url ?: null);
    }

    public function create(Request $request)
    {
        return Inertia::render('Calendar/Create', [
            'users' => User::all(['id', 'name']),
            // Pasamos la fecha seleccionada desde la URL, si existe
            'selectedDate' => $request->query('date'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'entry_type' => 'required|in:Evento,Tarea',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after_or_equal:start_datetime',
            'location' => 'required_if:entry_type,Evento|nullable|string|max:255',
            'participants' => 'required_if:entry_type,Evento|array',
            'participants.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($request) {
            $entryable = null;
            $currentUser = auth()->user();

            if ($request->entry_type === 'Evento') {
                $entryable = Event::create(['location' => $request->location]);
                
                // Sincronizamos a los participantes
                $entryable->participants()->sync($request->participants);

                // Si el creador está entre los participantes → marcar como Aceptado en el pivot
                if (in_array($currentUser->id, $request->participants)) {
                    $entryable->participants()->updateExistingPivot($currentUser->id, [
                        'status' => 'Aceptado',
                    ]);
                }
                
                // Creamos la entrada de calendario
                $entryable->calendarEntry()->create($request->only('title', 'description', 'color', 'start_datetime', 'end_datetime') + ['user_id' => $currentUser->id]);

                // --- LÓGICA DE NOTIFICACIÓN ---
                // Obtenemos los usuarios a notificar (todos los participantes menos el creador)
                $participantsToNotify = User::find(collect($request->participants)->reject(function ($id) use ($currentUser) {
                    return $id == $currentUser->id;
                }));

                // Enviamos la notificación a cada uno
                Notification::send($participantsToNotify, new EventInvitationNotification($entryable, $currentUser));
                
            } else {
                $entryable = Task::create(['status' => 'Pendiente']);
                $entryable->calendarEntry()->create($request->only('title', 'description', 'color', 'start_datetime', 'end_datetime') + ['user_id' => $currentUser->id]);
            }
        });

        return redirect()->route('calendar.index');
    }

    /**
     * Marca una tarea como completada.
     */
    public function updateTaskStatus(Task $task)
    {
        $task->update(['status' => 'Completada']);
        return redirect()->back();
    }

    /**
     * Actualiza el estado de la invitación de un usuario a un evento.
     */
    public function updateInvitationStatus(Request $request, Event $event)
    {
        // Validación de la entrada
        $request->validate(['status' => 'required|in:Aceptado,Rechazado']);
        
        $status = $request->status;
        $currentUser = Auth::user();

        // Actualizamos el estado en la tabla pivote para el usuario actual
        $event->participants()->updateExistingPivot($currentUser->id, [
            'status' => $status,
        ]);
        
        // Obtenemos al creador del evento a través de la relación polimórfica
        // Usamos load() para asegurar que la relación esté cargada y evitar consultas extra.
        $event->load('calendarEntry');
        $creator = User::find($event->calendarEntry->user_id);

        // Enviamos la notificación al creador (si no es el mismo usuario que responde)
        if ($creator && $creator !== $currentUser->id) {
            Notification::send($creator, new EventResponseNotification($event, $currentUser, $status));
        }        
        return redirect()->back();
    }

    /**
     * Elimina una entrada del calendario y su registro asociado (Evento o Tarea).
     */
    public function destroy(CalendarEntry $calendarEntry)
    {
        // La relación polimórfica se encargará de eliminar el Evento o Tarea asociada
        // si tienes configurado el borrado en cascada en la base de datos o en el modelo.
        // Por seguridad, lo hacemos explícito.
        DB::transaction(function () use ($calendarEntry) {
            $calendarEntry->entryable()->delete();
            $calendarEntry->delete();
        });

        return redirect()->route('calendar.index')->with('success', 'Entrada eliminada.');
    }
}
