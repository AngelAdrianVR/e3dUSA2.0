<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionShipment;
use App\Models\User;
use App\Notifications\RequisitionAuthorizedNotification;
use App\Notifications\RequisitionCommentMentionedNotification;
use App\Notifications\RequisitionCreatedNotification;
use App\Notifications\RequisitionInvoiceUploadedNotification;
use App\Notifications\RequisitionPaidNotification;
use App\Notifications\RequisitionQuotationUploadedNotification;
use App\Notifications\RequisitionRejectedNotification;
use App\Notifications\RequisitionSaleFrequencyUpdatedNotification;
use App\Support\RequisitionPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RequisitionController extends Controller
{
    /**
     * Colecciones de media capturadas al levantar la requisición.
     */
    private const CREATION_MEDIA_COLLECTIONS = ['authorization_formats', 'blueprints', 'brand_use_letters'];

    /**
     * Colecciones de media manejables desde la pestaña de documentos del detalle.
     */
    private const DOCUMENT_COLLECTIONS = [
        'authorization_formats',
        'blueprints',
        'brand_use_letters',
        'quotations',
        'packing_lists',
        'factory_invoices',
        'technical_sheets',
        'product_photos',
        'invoices',
        'payment_receipts',
    ];

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->hasAnyPermission(RequisitionPermissions::all()), 403);

        $canViewAll = RequisitionPermissions::canViewAll($user);
        $canViewDetails = RequisitionPermissions::canViewDetails($user);
        $view = $canViewAll && $request->input('view') !== 'mias' ? 'todas' : 'mias';

        $query = Requisition::query()->with([
            'user:id,name',
            'branch:id,name,business_name,parent_branch_id',
            'branch.parent:id,name,business_name',
            'items:id,requisition_id,product_id,quantity,arrival_status',
            'items.product:id,name,code',
            'shipments:id,tracking_number,status',
        ]);

        if ($view === 'mias') {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('origin')) {
            $query->where('origin', $request->input('origin'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Accesos rápidos del dashboard.
        if ($request->input('filter') === 'pending_payment') {
            $query->where('status', 'Pendiente de pago');
        }
        if ($request->input('filter') === 'pending_authorization') {
            $query->whereIn('status', ['Pendiente de autorización', 'Pendiente de negociación']);
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $numeric = preg_replace('/\D/', '', $search);

            $query->where(function ($q) use ($search, $numeric) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhereHas('branch', fn ($branch) => $branch->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.product', fn ($product) => $product
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn ($author) => $author->where('name', 'like', "%{$search}%"));

                if ($numeric !== '') {
                    $q->orWhere('id', (int) $numeric);
                }
            });
        }

        $requisitions = $query->latest()->paginate(15)->withQueryString();

        // Banderas por fila: habilitan las acciones y la edición en línea del listado.
        $requisitions->through(function (Requisition $requisition) use ($user, $canViewDetails) {
            $row = [
                ...$requisition->toArray(),
                'can' => [
                    'edit' => $this->canEditRequisition($requisition, $user),
                    'delete' => $this->canDeleteRequisition($requisition, $user),
                    // La frecuencia de venta solo aplica a requisiciones de vendedor.
                    'edit_frequency' => $requisition->origin === Requisition::ORIGIN_SELLER
                        && $this->canEditRequisition($requisition, $user),
                ],
            ];

            // Los montos solo se envían a quienes pueden abrir el detalle.
            if (! $canViewDetails) {
                unset(
                    $row['quoted_cost'],
                    $row['quoted_currency'],
                    $row['quoted_tax_enabled'],
                    $row['quoted_tax_rate'],
                    $row['quoted_tax_amount'],
                    $row['quoted_total'],
                    $row['invoice_amount'],
                    $row['invoice_currency'],
                );
            }

            return $row;
        });

        return Inertia::render('Requisition/Index', [
            'requisitions' => $requisitions,
            'filters' => $request->only(['view', 'type', 'status', 'origin', 'priority', 'search', 'filter']),
            'statusOptions' => array_values(array_unique(array_merge(Requisition::LOCAL_STATUSES, Requisition::IMPORT_STATUSES))),
            'salesFrequencies' => Requisition::SALES_FREQUENCIES,
            'can' => [
                'create' => RequisitionPermissions::canCreate($user),
                'view_all' => $canViewAll,
                'view_details' => $canViewDetails,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canCreate($user), 403);

        $type = $request->query('type') === Requisition::TYPE_IMPORT
            ? Requisition::TYPE_IMPORT
            : Requisition::TYPE_LOCAL;

        return Inertia::render('Requisition/Create', [
            'type' => $type,
            'branches' => Branch::with('parent:id,name,business_name')
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'parent_branch_id']),
            'products' => $this->productOptions(),
            'priorities' => Requisition::PRIORITIES,
            'salesFrequencies' => Requisition::SALES_FREQUENCIES,
        ]);
    }

    /**
     * Productos padre comprables con imagen, stock actual y stock mínimo.
     */
    private function productOptions()
    {
        return Product::with(['storages', 'media'])
            ->whereNull('parent_id')
            ->where('is_purchasable', true)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'image_url' => $product->getFirstMediaUrl('images')
                    ?: ($product->media->first()?->original_url ?: asset('images/image.png')),
                'current_stock' => (float) $product->storages->sum('quantity'),
                'min_quantity' => (float) $product->min_quantity,
                'measure_unit' => $product->measure_unit,
            ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canCreate($user), 403);

        $this->validateRequisition($request);

        $status = $request->type === Requisition::TYPE_IMPORT
            ? 'Pendiente de negociación'
            : 'Pendiente de cotización';

        $isWarehouse = $request->origin === Requisition::ORIGIN_WAREHOUSE;

        // Una requisición puede solicitar varios productos: la cantidad total es la suma.
        $totalQuantity = $isWarehouse
            ? collect($request->items)->sum(fn ($item) => (float) $item['quantity'])
            : (float) $request->quantity;

        $requisition = DB::transaction(function () use ($request, $user, $status, $isWarehouse, $totalQuantity) {
            $requisition = Requisition::create([
                'type' => $request->type,
                'origin' => $request->origin,
                'status' => $status,
                'priority' => $isWarehouse ? $request->priority : null,
                'user_id' => $user->id,
                'branch_id' => $request->origin === Requisition::ORIGIN_SELLER ? $request->branch_id : null,
                'business_name' => $this->resolveBusinessName($request->branch_id),
                'product_id' => null,
                'quantity' => $totalQuantity,
                'reason' => $isWarehouse ? $request->reason : null,
                'sale_specification' => $request->sale_specification,
                // La frecuencia de venta solo aplica a requisiciones de vendedor.
                'sale_frequency' => $isWarehouse ? null : $request->sale_frequency,
                'notes' => $request->notes,
            ]);

            $this->storeItems($requisition, $request, $isWarehouse);
            $this->storeMedia($request, $requisition, self::CREATION_MEDIA_COLLECTIONS);

            return $requisition;
        });

        $this->notifyCreation($requisition, $user);

        return redirect(RequisitionPermissions::urlFor($user, $requisition))
            ->with('success', "Requisición {$requisition->folio} creada correctamente.");
    }

    public function show(Requisition $requisition)
    {
        $user = Auth::user();

        // Solo la gestión de compras puede abrir el detalle; el resto usa el listado.
        if (! RequisitionPermissions::canViewDetails($user)) {
            return redirect()->route('requisitions.index')
                ->with('error', 'No tienes permiso para ver el detalle de las requisiciones.');
        }

        $requisition->load([
            'user:id,name',
            'branch:id,name,business_name,parent_branch_id',
            'branch.parent:id,name,business_name',
            'authorizer:id,name',
            'rejecter:id,name',
            'comments.user:id,name',
            'items.product.media',
            'items.shipments:id,tracking_number,carrier,status',
            'shipments:id,tracking_number,carrier,status,estimated_arrival_date',
            'media',
        ]);

        return Inertia::render('Requisition/Show', [
            'requisition' => $requisition,
            'can' => $this->actionFlags($requisition, $user),
            'mentionableUsers' => $this->mentionableUsers($requisition),
            'shipmentStatuses' => RequisitionShipment::STATUSES,
            'shipmentOptions' => RequisitionShipment::orderByDesc('created_at')->get(['id', 'tracking_number', 'carrier', 'status', 'estimated_arrival_date']),
        ]);
    }

    public function edit(Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless($this->canEditRequisition($requisition, $user), 403);

        $requisition->load([
            'media',
            'branch:id,name,business_name,parent_branch_id',
            'items.product:id,name,code',
            'items.shipments:id,tracking_number',
        ]);

        return Inertia::render('Requisition/Edit', [
            'requisition' => $requisition,
            'branches' => Branch::with('parent:id,name,business_name')
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'parent_branch_id']),
            'products' => $this->productOptions(),
            'priorities' => Requisition::PRIORITIES,
            'salesFrequencies' => Requisition::SALES_FREQUENCIES,
        ]);
    }

    public function update(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless($this->canEditRequisition($requisition, $user), 403);

        $this->validateRequisition($request, mediaRequired: false);

        $wasRejected = $requisition->status === 'Rechazada';
        $isWarehouse = $request->origin === Requisition::ORIGIN_WAREHOUSE;

        $totalQuantity = $isWarehouse
            ? collect($request->items)->sum(fn ($item) => (float) $item['quantity'])
            : (float) $request->quantity;

        DB::transaction(function () use ($request, $requisition, $wasRejected, $isWarehouse, $totalQuantity) {
            $payload = [
                'priority' => $isWarehouse ? $request->priority : null,
                'branch_id' => $request->origin === Requisition::ORIGIN_SELLER ? $request->branch_id : null,
                'business_name' => $this->resolveBusinessName($request->branch_id),
                'quantity' => $totalQuantity,
                'reason' => $isWarehouse ? $request->reason : null,
                'sale_specification' => $request->sale_specification,
                // La frecuencia de venta solo aplica a requisiciones de vendedor.
                'sale_frequency' => $isWarehouse ? null : $request->sale_frequency,
                'notes' => $request->notes,
            ];

            // Re-trabajo del rechazo: se conserva el folio y regresa al inicio del flujo.
            if ($wasRejected) {
                $payload['status'] = $requisition->type === Requisition::TYPE_IMPORT
                    ? 'Pendiente de negociación'
                    : 'Pendiente de cotización';
            }

            $requisition->update($payload);

            $this->updateItems($requisition, $request, $isWarehouse);

            foreach (self::CREATION_MEDIA_COLLECTIONS as $collection) {
                if ($request->hasFile($collection)) {
                    $requisition->clearMediaCollection($collection);
                }
            }
            $this->storeMedia($request, $requisition, self::CREATION_MEDIA_COLLECTIONS);
        });

        if ($wasRejected) {
            $this->storeComment($requisition, $user, 'Requisición re-trabajada sobre el mismo folio tras el rechazo.');
            $this->notifyCreation($requisition, $user);
        }

        return redirect(RequisitionPermissions::urlFor($user, $requisition))
            ->with('success', "Requisición {$requisition->folio} actualizada.");
    }

    public function destroy(Requisition $requisition)
    {
        $user = Auth::user();

        abort_unless($this->canDeleteRequisition($requisition, $user), 403);

        $folio = $requisition->folio;
        $requisition->delete();

        return redirect()->route('requisitions.index')->with('success', "Requisición {$folio} eliminada.");
    }

    /**
     * Edición en línea (listado) de la frecuencia de venta / consumo.
     */
    public function updateSaleFrequency(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless($this->canEditRequisition($requisition, $user), 403);
        abort_unless($requisition->origin === Requisition::ORIGIN_SELLER, 409, 'La frecuencia de venta solo aplica a requisiciones de vendedor.');

        $validated = $request->validate([
            'sale_frequency' => ['nullable', Rule::in(Requisition::SALES_FREQUENCIES)],
        ]);

        $previousFrequency = $requisition->sale_frequency;

        $requisition->update(['sale_frequency' => $validated['sale_frequency'] ?? null]);

        // El cambio de frecuencia impacta la planeación de compras: se avisa a
        // quienes tienen el permiso de gestión de compras.
        if ($requisition->wasChanged('sale_frequency')) {
            $recipients = RequisitionPermissions::usersToNotify(
                RequisitionPermissions::PURCHASING_MANAGEMENT,
                $user
            );

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new RequisitionSaleFrequencyUpdatedNotification($requisition, $previousFrequency));
            }
        }

        return back()->with('success', 'Frecuencia actualizada.');
    }

    /**
     * Adjunta archivos sueltos desde la pestaña de documentos del detalle.
     */
    public function uploadMedia(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless($this->canEditRequisition($requisition, $user), 403);

        $request->validate([
            'collection' => ['required', 'string', Rule::in(self::DOCUMENT_COLLECTIONS)],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ]);

        foreach ($request->file('files') as $file) {
            $requisition->addMedia($file)->toMediaCollection($request->collection);
        }

        return back()->with('success', 'Archivo(s) adjuntado(s).');
    }

    /**
     * Actualiza el estatus de arribo de una partida (detalle del calendario de arribos).
     */
    public function updateItemArrivalStatus(Request $request, RequisitionItem $item)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user), 403);

        $validated = $request->validate([
            'arrival_status' => ['required', Rule::in(Requisition::ARRIVAL_STATUSES)],
            'expected_delivery_date' => ['nullable', 'date'],
            'arrival_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $item->update([
            'arrival_status' => $validated['arrival_status'],
            'arrival_notes' => $validated['arrival_notes'] ?? null,
        ]);

        $requisition = $item->requisition;

        if ($requisition) {
            if (! empty($validated['expected_delivery_date'])) {
                $requisition->update(['expected_delivery_date' => $validated['expected_delivery_date']]);
            }

            $requisition->refreshArrivalStatusFromItems();
        }

        return back()->with('success', 'Arribo actualizado.');
    }

    /**
     * Compras adjunta la cotización del proveedor.
     *
     * Local: la cotización habilita la validación gerencial.
     * Importación: la captura la gerencia de compras y no altera la etapa.
     */
    public function uploadQuotation(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        $isLocal = $requisition->type === Requisition::TYPE_LOCAL;

        // La importación la cotiza la gerencia de compras; la local, cualquier editor.
        abort_unless(
            $isLocal
                ? RequisitionPermissions::canEdit($user)
                : RequisitionPermissions::canQuote($user),
            403
        );

        abort_if($this->hasQuotation($requisition), 409, 'La requisición ya tiene una cotización adjunta.');

        $quotableStatuses = $isLocal
            ? ['Pendiente de cotización', 'Rechazada', 'Pendiente de autorización']
            : ['Pendiente de negociación', 'Rechazada', 'Aprobada'];

        abort_unless(in_array($requisition->status, $quotableStatuses, true), 409, 'La requisición no está en etapa de cotización.');

        $request->validate([
            'quotation' => ['required', 'file', 'max:10240'],
            'quoted_cost' => ['nullable', 'numeric', 'min:0'],
            'quoted_currency' => ['nullable', 'string', 'max:3'],
            // IVA de la cotización: activable y con porcentaje editable (16% por defecto).
            'quoted_tax_enabled' => ['nullable', 'boolean'],
            'quoted_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Costo unitario y descripción por producto cotizado.
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:requisition_items,id'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $requisition, $isLocal) {
            $requisition->addMediaFromRequest('quotation')->toMediaCollection('quotations');

            $payload = [
                'quoted_cost' => $request->quoted_cost,
                'quoted_currency' => $request->quoted_currency,
                'quoted_tax_enabled' => $request->boolean('quoted_tax_enabled'),
                'quoted_tax_rate' => $request->filled('quoted_tax_rate') ? $request->quoted_tax_rate : 16,
            ];

            // Solo el flujo local avanza a validación gerencial.
            if ($isLocal) {
                $payload['status'] = 'Pendiente de autorización';
            }

            $requisition->update($payload);

            $this->storeQuotationItems($requisition, $request->items ?? []);
        });

        $this->storeComment($requisition, $user, $isLocal
            ? 'Cotización adjuntada; enviada a autorización.'
            : 'Cotización adjuntada por la gerencia de compras.');

        $this->notify(
            $isLocal ? RequisitionPermissions::AUTHORIZE : RequisitionPermissions::EDIT,
            $user,
            new RequisitionQuotationUploadedNotification($requisition, $isLocal)
        );

        return back()->with('success', $isLocal
            ? 'Cotización adjuntada. Se notificó a la gerencia de compras.'
            : 'Cotización adjuntada.');
    }

    /**
     * Validación gerencial (Pass/Fail). El rechazo no genera folio nuevo.
     */
    public function authorizeRequisition(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canAuthorize($user), 403);

        $request->validate([
            'result' => ['required', Rule::in(['pass', 'fail'])],
            'rejection_reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('result') === 'fail')],
        ]);

        $authorizable = $this->authorizableStatuses($requisition->type === Requisition::TYPE_LOCAL);

        abort_unless(in_array($requisition->status, $authorizable, true), 409, 'La requisición no está en una etapa autorizable.');

        if ($request->input('result') === 'fail') {
            DB::transaction(function () use ($request, $requisition, $user) {
                $requisition->update([
                    'status' => 'Rechazada',
                    'rejection_reason' => $request->rejection_reason,
                    'rejected_by_user_id' => $user->id,
                    'rejected_at' => now(),
                ]);
                $this->storeComment($requisition, $user, "Requisición rechazada: {$request->rejection_reason}");
            });

            $recipients = $this->notifiedUsers(RequisitionPermissions::EDIT, $user)
                ->push($requisition->user)
                ->filter()
                ->unique('id');
            Notification::send($recipients, new RequisitionRejectedNotification($requisition));

            return back()->with('warning', 'Requisición rechazada. Se notificó al solicitante; debe editar la requisición para volver a habilitar las acciones.');
        }

        $newStatus = $requisition->type === Requisition::TYPE_LOCAL ? 'Autorizada' : 'Aprobada';

        $requisition->update([
            'status' => $newStatus,
            'authorized_by_user_id' => $user->id,
            'authorized_at' => now(),
        ]);

        $this->storeComment($requisition, $user, "Requisición {$newStatus}.");

        $recipients = $this->notifiedUsers(RequisitionPermissions::EDIT, $user)
            ->push($requisition->user)
            ->filter()
            ->unique('id');
        Notification::send($recipients, new RequisitionAuthorizedNotification($requisition));

        return back()->with('success', "Requisición {$newStatus}.");
    }

    /**
     * Importación: Wendy registra el pedido a fábrica tras la negociación.
     */
    public function markFactoryOrder(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canAuthorize($user) || RequisitionPermissions::canManageArrivals($user), 403);
        abort_unless($requisition->type === Requisition::TYPE_IMPORT && $requisition->status === 'Aprobada', 409, 'La requisición no está en etapa de pedido a fábrica.');

        $validated = $request->validate([
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $requisition->update([
            'status' => 'Pedido a fábrica',
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
        ]);

        $this->storeComment($requisition, $user, 'Pedido realizado a fábrica. Se solicita documentación de seguimiento (packing list, invoice, fichas técnicas, fotos).');

        $this->notify(RequisitionPermissions::EDIT, $user, new RequisitionAuthorizedNotification($requisition));

        return back()->with('success', 'Pedido a fábrica registrado.');
    }

    /**
     * Importación: documentación de seguimiento de paquetería.
     */
    public function uploadFactoryDocs(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canManageArrivals($user) || RequisitionPermissions::canEdit($user), 403);
        abort_unless($requisition->type === Requisition::TYPE_IMPORT, 403, 'La documentación de fábrica solo aplica a importaciones.');
        abort_unless(in_array($requisition->status, ['Aprobada', 'Pedido a fábrica', 'En tránsito'], true), 409, 'La requisición no está en etapa de documentación.');

        $request->validate([
            'packing_lists' => ['nullable', 'array'],
            'packing_lists.*' => ['file', 'max:10240'],
            'factory_invoices' => ['nullable', 'array'],
            'factory_invoices.*' => ['file', 'max:10240'],
            'technical_sheets' => ['nullable', 'array'],
            'technical_sheets.*' => ['file', 'max:10240'],
            'product_photos' => ['nullable', 'array'],
            'product_photos.*' => ['file', 'max:10240'],
            'expected_delivery_date' => ['nullable', 'date'],
        ]);

        $collections = ['packing_lists', 'factory_invoices', 'technical_sheets', 'product_photos'];
        $hasFiles = collect($collections)->contains(fn ($collection) => $request->hasFile($collection));

        if (! $hasFiles) {
            return back()->withErrors(['packing_lists' => 'Adjunta al menos un documento de seguimiento.']);
        }

        DB::transaction(function () use ($request, $requisition, $collections) {
            foreach ($collections as $collection) {
                if ($request->hasFile($collection)) {
                    foreach ($request->file($collection) as $file) {
                        $requisition->addMedia($file)->toMediaCollection($collection);
                    }
                }
            }

            $payload = [];

            if ($request->filled('expected_delivery_date')) {
                $payload['expected_delivery_date'] = $request->expected_delivery_date;
            }

            // Con el packing list en mano la mercancía ya va en tránsito.
            if ($request->hasFile('packing_lists') && $requisition->status === 'Pedido a fábrica') {
                $payload['status'] = 'En tránsito';
                $payload['arrival_status'] = 'En tránsito';
            }

            if ($payload !== []) {
                $requisition->update($payload);
            }
        });

        $this->storeComment($requisition, $user, 'Documentación de fábrica adjuntada (packing list, invoice, fichas técnicas y/o fotos).');

        $this->notify(RequisitionPermissions::EDIT, $user, new RequisitionAuthorizedNotification($requisition));

        return back()->with('success', 'Documentación de seguimiento adjuntada.');
    }

    /**
     * Local: factura del proveedor; dispara el pago en tesorería.
     */
    public function uploadInvoice(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canEdit($user), 403);
        abort_unless($requisition->type === Requisition::TYPE_LOCAL, 403, 'La factura solo aplica a compras locales.');
        abort_unless(in_array($requisition->status, ['Autorizada', 'Pendiente de pago'], true), 409, 'La requisición debe estar autorizada para registrar la factura.');

        $request->validate([
            'invoice' => ['required', 'file', 'max:10240'],
            'invoice_amount' => ['nullable', 'numeric', 'min:0'],
            'invoice_currency' => ['nullable', 'string', 'max:3'],
        ]);

        DB::transaction(function () use ($request, $requisition) {
            $requisition->addMediaFromRequest('invoice')->toMediaCollection('invoices');
            $requisition->update([
                'status' => 'Pendiente de pago',
                'invoice_amount' => $request->invoice_amount,
                'invoice_currency' => $request->invoice_currency,
            ]);
        });

        $this->storeComment($requisition, $user, 'Factura del proveedor adjuntada; se notificó a pagos.');

        $this->notify(RequisitionPermissions::PAY, $user, new RequisitionInvoiceUploadedNotification($requisition));

        return back()->with('success', 'Factura adjuntada. Se notificó a tesorería.');
    }

    /**
     * Tesorería registra el pago (comprobante + fecha).
     */
    public function registerPayment(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canRegisterPayments($user), 403);
        abort_unless($requisition->status === 'Pendiente de pago', 409, 'La requisición no está pendiente de pago.');

        $request->validate([
            'payment_receipt' => ['required', 'file', 'max:10240'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $requisition) {
            $requisition->addMediaFromRequest('payment_receipt')->toMediaCollection('payment_receipts');
            $requisition->update([
                'status' => 'Pagada',
                'paid_at' => $request->paid_at,
            ]);
        });

        $this->storeComment($requisition, $user, 'Comprobante de pago adjuntado. Se comparte con el proveedor y se solicita fecha de entrega.');

        $recipients = $this->notifiedUsers(array_merge(RequisitionPermissions::EDIT, RequisitionPermissions::AUTHORIZE), $user)
            ->push($requisition->user)
            ->filter()
            ->unique('id');
        Notification::send($recipients, new RequisitionPaidNotification($requisition));

        return back()->with('success', 'Pago registrado correctamente.');
    }

    /**
     * Fecha estimada de entrega / arribo de la requisición.
     */
    public function registerDeliveryDate(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canEdit($user) || RequisitionPermissions::canManageArrivals($user), 403);
        abort_unless(! in_array($requisition->status, ['Completada', 'Rechazada'], true), 409, 'La requisición no admite fecha de entrega en su etapa actual.');

        $request->validate([
            'expected_delivery_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $requisition->update(['expected_delivery_date' => $request->expected_delivery_date]);

        $this->storeComment($requisition, $user, 'Fecha estimada de entrega actualizada: ' . $request->expected_delivery_date . '.');

        $recipients = $this->notifiedUsers(RequisitionPermissions::EDIT, $user)
            ->push($requisition->user)
            ->filter()
            ->unique('id');
        Notification::send($recipients, new RequisitionAuthorizedNotification($requisition));

        return back()->with('success', 'Fecha de entrega registrada.');
    }

    /**
     * Cierre de la requisición (recibida / arribo confirmado).
     */
    public function complete(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canEdit($user) || RequisitionPermissions::canManageArrivals($user), 403);

        $completable = $requisition->type === Requisition::TYPE_LOCAL
            ? in_array($requisition->status, ['Pagada'], true)
            : in_array($requisition->status, ['Arribo confirmado'], true);

        abort_unless($completable, 409, 'La requisición no está lista para cerrarse.');

        $requisition->update([
            'status' => 'Completada',
            'delivered_at' => now(),
        ]);

        $this->storeComment($requisition, $user, 'Requisición completada: mercancía recibida.');

        $recipients = $this->notifiedUsers(array_merge(RequisitionPermissions::EDIT, RequisitionPermissions::AUTHORIZE), $user)
            ->push($requisition->user)
            ->filter()
            ->unique('id');
        Notification::send($recipients, new RequisitionAuthorizedNotification($requisition));

        return back()->with('success', 'Requisición completada.');
    }

    /**
     * Comentario en el hilo de la requisición (con menciones @).
     */
    public function addComment(Request $request, Requisition $requisition)
    {
        $user = Auth::user();
        abort_unless(RequisitionPermissions::canViewDetails($user), 403);

        $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'mentioned_user_ids' => ['nullable', 'array'],
            'mentioned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Menciones válidas: usuarios con acceso al módulo (o el solicitante) y sin auto-mención.
        $allowedIds = $this->mentionableUserIds($requisition);

        $mentionedIds = collect($request->mentioned_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $user->id)
            ->filter(fn ($id) => in_array($id, $allowedIds, true))
            ->unique()
            ->values();

        $comment = $this->storeComment($requisition, $user, $request->content, $mentionedIds->all());

        if ($mentionedIds->isNotEmpty()) {
            $comment->setRelation('user', $user);

            User::whereIn('id', $mentionedIds)->get()->each(
                fn (User $mentioned) => $mentioned->notify(new RequisitionCommentMentionedNotification($comment, $requisition))
            );
        }

        return back()->with('success', $mentionedIds->isEmpty()
            ? 'Comentario agregado.'
            : 'Comentario agregado y se notificó a ' . $mentionedIds->count() . ' usuario(s).');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function validateRequisition(Request $request, bool $mediaRequired = true): void
    {
        $isSeller = $request->origin === Requisition::ORIGIN_SELLER;
        $isWarehouse = $request->origin === Requisition::ORIGIN_WAREHOUSE;
        $isImportBySeller = $isSeller && $request->type === Requisition::TYPE_IMPORT;

        $required = $mediaRequired ? 'required' : 'nullable';

        $request->validate([
            'type' => ['required', Rule::in([Requisition::TYPE_LOCAL, Requisition::TYPE_IMPORT])],
            'origin' => ['required', Rule::in([Requisition::ORIGIN_SELLER, Requisition::ORIGIN_WAREHOUSE])],
            'branch_id' => ['nullable', Rule::requiredIf($isSeller), 'exists:branches,id'],
            'quantity' => ['nullable', Rule::requiredIf($isSeller), 'numeric', 'min:0.01'],
            'reason' => ['nullable', Rule::requiredIf($isWarehouse), 'string', 'max:2000'],
            'priority' => ['nullable', Rule::requiredIf($isWarehouse), Rule::in(Requisition::PRIORITIES)],
            'sale_specification' => ['nullable', 'string', 'max:2000'],
            'sale_frequency' => ['nullable', Rule::in(Requisition::SALES_FREQUENCIES)],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Vía almacén / producción: lista de productos solicitados (mínimo uno).
            'items' => ['nullable', 'array', Rule::requiredIf($isWarehouse)],
            'items.*.id' => ['nullable', 'integer', 'exists:requisition_items,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],

            // Vía vendedor: formato de autorización y plano.
            'authorization_formats' => ['nullable', 'array', Rule::requiredIf($isSeller && $mediaRequired)],
            'authorization_formats.*' => [$required, 'file', 'max:10240'],
            'blueprints' => ['nullable', 'array', Rule::requiredIf($isSeller && $mediaRequired)],
            'blueprints.*' => [$required, 'file', 'max:10240'],

            // Importación vía vendedor: carta de uso de marca obligatoria.
            'brand_use_letters' => ['nullable', 'array', Rule::requiredIf($isImportBySeller && $mediaRequired)],
            'brand_use_letters.*' => [$required, 'file', 'max:10240'],
        ], [
            'branch_id.required' => 'Selecciona el cliente para las requisiciones de venta.',
            'quantity.required' => 'Indica la cantidad de piezas solicitadas.',
            'items.required' => 'Agrega al menos un producto a la requisición.',
            'items.*.product_id.required' => 'Selecciona el producto en cada partida.',
            'items.*.quantity.required' => 'Indica la cantidad de cada producto.',
            'reason.required' => 'Indica el motivo de la requisición.',
            'sale_frequency.in' => 'Selecciona una frecuencia de venta / consumo válida.',
            'priority.required' => 'Selecciona la prioridad.',
            'authorization_formats.required' => 'Adjunta el formato de autorización.',
            'blueprints.required' => 'Adjunta el plano o render para especificaciones.',
            'brand_use_letters.required' => 'La carta de uso de marca es obligatoria para requisiciones de importación.',
        ]);
    }

    /**
     * Crea las partidas de la requisición. La vía vendedor genera una sola
     * partida (sin producto de catálogo) para mantener el mismo modelo de guías.
     */
    private function storeItems(Requisition $requisition, Request $request, bool $isWarehouse): void
    {
        if ($isWarehouse) {
            foreach ($request->items as $item) {
                $requisition->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return;
        }

        $requisition->items()->create([
            'product_id' => null,
            'quantity' => $request->quantity,
        ]);
    }

    /**
     * Reconcilia la lista de productos al editar: actualiza las partidas
     * existentes, agrega nuevas y elimina solo las que no tienen guía.
     */
    private function updateItems(Requisition $requisition, Request $request, bool $isWarehouse): void
    {
        if (! $isWarehouse) {
            $item = $requisition->items()->firstOrNew([]);
            $item->product_id = null;
            $item->quantity = $request->quantity;
            $item->save();

            return;
        }

        $payloadItems = collect($request->items);
        $keepIds = $payloadItems->pluck('id')->filter()->values()->all();

        foreach ($payloadItems as $item) {
            if (! empty($item['id'])) {
                $requisition->items()->whereKey($item['id'])->update([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);

                continue;
            }

            $created = $requisition->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
            ]);

            $keepIds[] = $created->id;
        }

        // Las partidas ya ligadas a una guía no se eliminan desde la edición.
        $requisition->items()
            ->whereNotIn('id', $keepIds)
            ->whereDoesntHave('shipments')
            ->delete();

        $requisition->refreshArrivalStatusFromItems();
    }

    private function storeMedia(Request $request, Requisition $requisition, array $collections): void
    {
        foreach ($collections as $collection) {
            if ($request->hasFile($collection)) {
                foreach ($request->file($collection) as $file) {
                    $requisition->addMedia($file)->toMediaCollection($collection);
                }
            }
        }
    }

    /**
     * Costo unitario por producto capturado al adjuntar la cotización.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function storeQuotationItems(Requisition $requisition, array $items): void
    {
        $ownItemIds = $requisition->items()->pluck('id')->all();

        foreach ($items as $item) {
            if (! in_array((int) ($item['id'] ?? 0), $ownItemIds, true)) {
                continue;
            }

            $requisition->items()->whereKey($item['id'])->update([
                'unit_cost' => $item['unit_cost'] ?? null,
                'description' => $item['description'] ?? null,
            ]);
        }
    }

    private function resolveBusinessName(?int $branchId): ?string
    {
        if (! $branchId) {
            return null;
        }

        $branch = Branch::with('parent:id,business_name')->find($branchId);

        return $branch?->business_name ?: $branch?->parent?->business_name;
    }

    private function canEditRequisition(Requisition $requisition, User $user): bool
    {
        if ($requisition->status === 'Completada') {
            return false;
        }

        if (RequisitionPermissions::canEdit($user)) {
            return true;
        }

        return $requisition->user_id === $user->id && in_array($requisition->status, [
            'Pendiente de cotización',
            'Pendiente de negociación',
            'Pendiente de autorización',
            'Rechazada',
        ], true);
    }

    private function canDeleteRequisition(Requisition $requisition, User $user): bool
    {
        return RequisitionPermissions::canEdit($user)
            || ($requisition->user_id === $user->id && in_array($requisition->status, [
                'Pendiente de cotización',
                'Pendiente de negociación',
                'Rechazada',
            ], true));
    }

    /**
     * Estatus desde los que la gerencia puede registrar la validación (Pass/Fail).
     *
     * Local: la cotización mueve la requisición a "Pendiente de autorización".
     * Importación: la cotización no altera la etapa, así que una requisición
     * rechazada también se puede aprobar o volver a rechazar sin editarla.
     */
    private function authorizableStatuses(bool $isLocal): array
    {
        return $isLocal
            ? ['Pendiente de autorización']
            : ['Pendiente de negociación', 'Aprobada', 'Rechazada'];
    }

    /**
     * Indica si la requisición ya tiene una cotización adjunta.
     */
    private function hasQuotation(Requisition $requisition): bool
    {
        return $requisition->media()->where('collection_name', 'quotations')->exists();
    }

    /**
     * Banderas de acción que consume la vista de detalle.
     */
    private function actionFlags(Requisition $requisition, User $user): array
    {
        $canEdit = $this->canEditRequisition($requisition, $user);
        $canAuthorize = RequisitionPermissions::canAuthorize($user);
        $canPay = RequisitionPermissions::canRegisterPayments($user);
        $canManageArrivals = RequisitionPermissions::canManageArrivals($user);
        $isLocal = $requisition->type === Requisition::TYPE_LOCAL;
        $hasQuotation = $this->hasQuotation($requisition);

        // La cotización se captura una sola vez por requisición.
        $canQuote = $isLocal
            ? RequisitionPermissions::canEdit($user)
                && in_array($requisition->status, ['Pendiente de cotización', 'Rechazada', 'Pendiente de autorización'], true)
            : RequisitionPermissions::canQuote($user)
                && in_array($requisition->status, ['Pendiente de negociación', 'Rechazada', 'Aprobada'], true);

        return [
            'edit' => $canEdit,
            'delete' => $this->canDeleteRequisition($requisition, $user),
            'upload_quotation' => $canQuote && ! $hasQuotation,
            'quoted' => $hasQuotation,
            'authorize' => $canAuthorize && in_array($requisition->status, $this->authorizableStatuses($isLocal), true),
            'factory_order' => ($canAuthorize || $canManageArrivals) && ! $isLocal && $requisition->status === 'Aprobada',
            'upload_factory_docs' => ($canManageArrivals || RequisitionPermissions::canEdit($user))
                && ! $isLocal
                && in_array($requisition->status, ['Aprobada', 'Pedido a fábrica', 'En tránsito'], true),
            'upload_invoice' => RequisitionPermissions::canEdit($user)
                && $isLocal
                && in_array($requisition->status, ['Autorizada', 'Pendiente de pago'], true),
            'register_payment' => $canPay && $requisition->status === 'Pendiente de pago',
            'register_delivery' => ($canEdit || $canManageArrivals) && ! in_array($requisition->status, ['Completada', 'Rechazada'], true),
            'complete' => ($canEdit || $canManageArrivals) && ($isLocal
                ? $requisition->status === 'Pagada'
                : $requisition->status === 'Arribo confirmado'),
            'manage_arrivals' => $canManageArrivals,
            'comment' => true,
        ];
    }

    private function storeComment(Requisition $requisition, User $user, string $content, array $mentionedUserIds = []): Comment
    {
        // El modelo Comment no permite asignación masiva; se instancia y se asigna directo.
        $comment = new Comment();
        $comment->content = $content;
        $comment->user_id = $user->id;
        $comment->mentioned_user_ids = $mentionedUserIds;
        $requisition->comments()->save($comment);

        return $comment;
    }

    /**
     * Usuarios que pueden ser mencionados: quienes tienen acceso al módulo
     * (para que puedan abrir la requisición) más el solicitante.
     */
    private function mentionableUsers(Requisition $requisition)
    {
        $users = User::permission(RequisitionPermissions::all())
            ->where('is_active', true)
            ->get();

        if ($requisition->user && ! $users->contains('id', $requisition->user->id)) {
            $users->push($requisition->user);
        }

        return $users->sortBy('name')->values()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'profile_photo_url' => $user->profile_photo_url,
        ])->values();
    }

    /**
     * IDs válidos para mencionar en la requisición.
     */
    private function mentionableUserIds(Requisition $requisition): array
    {
        return $this->mentionableUsers($requisition)->pluck('id')->all();
    }

    /**
     * Notificaciones de alta y re-trabajo.
     */
    private function notifyCreation(Requisition $requisition, User $author): void
    {
        $permissions = $requisition->type === Requisition::TYPE_IMPORT
            ? RequisitionPermissions::AUTHORIZE
            : array_merge(['Ver requisiciones de compra', 'Gestión de almacén'], RequisitionPermissions::EDIT);

        $this->notify($permissions, $author, new RequisitionCreatedNotification($requisition));
    }

    /**
     * Envía una notificación a los usuarios activos con los permisos indicados,
     * excluyendo al autor de la acción.
     */
    private function notify(array $permissions, User $author, $notification): void
    {
        $recipients = $this->notifiedUsers($permissions, $author);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notification);
        }
    }

    private function notifiedUsers(array $permissions, User $author)
    {
        return RequisitionPermissions::usersToNotify($permissions, $author);
    }
}
