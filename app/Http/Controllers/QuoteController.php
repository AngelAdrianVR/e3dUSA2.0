<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteProduct;
use App\Models\User;
use App\Notifications\ApprovalQuoteNotification;
use App\Notifications\NewQuoteForApprovalNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        // Determina si se deben mostrar todas las cotizaciones o solo las del usuario.
        $showAll = $request->query('view') === 'all';
        
        $query = Quote::query();

        // --- MODIFICACIÓN 1: Solo mostrar cotizaciones ACTIVAS ---
        // Esto evita que el index se llene de v1, v2, v3...
        $query->where('is_active', true);

        // Si no se solicita ver todo, filtra por el usuario autenticado.
        if (!$showAll) {
            $query->where('user_id', Auth::id());
        }

        // --- MODIFICACIÓN 2: Cargar conteo de versiones ---
        // Esto agrega un atributo 'all_versions_count' a cada cotización
        $query->withCount('allVersions');

        // Se obtienen las cotizaciones paginadas
        $quotes = $query->with(['branch:id,name,status', 'user:id,name', 'sale:id', 'authorizedBy:id,name', 'products:id,name,cost'])
            ->latest()
            // Se eliminó el ->select() para asegurar que 'withCount' y
            // los accessors del modelo (como total_data) funcionen correctamente.
            ->paginate(20)
            ->withQueryString(); // Mantiene los query params (ej. `view=all`) en la paginación

        // Retornamos la vista de Inertia, pasando los datos y los filtros.
        return Inertia::render('Quote/Index', [
            'quotes' => $quotes,
            'filters' => $request->only(['view']), // Pasa los filtros actuales a la vista
        ]);
    }

    public function create()
    {
        // Obtenemos solo los productos de tipo 'Catálogo' para el selector principal.
        $catalogProducts = Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name', 'code')->get();
        
        // Obtenemos todas las sucursales (clientes).
        $branches = Branch::select('id', 'name', 'status')->get();

        return Inertia::render('Quote/Create', [
            'catalogProducts' => $catalogProducts,
            'branches' => $branches,
        ]);
    }

    /**
     * Guarda una cotización por PRIMERA VEZ (Crea la v1).
     */
    public function store(Request $request)
    {
        // Tu validación (sin cambios)
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'receiver' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'tooling_cost' => 'nullable|string|min:0',
            'freight_cost' => 'required_unless:freight_option,El cliente manda la guia,Client sends the shipping label,Por cuenta del cliente,Paid by the client|nullable|numeric|min:0',
            'freight_option' => 'required|string',
            'first_production_days' => 'required|string',
            'has_early_payment_discount' => 'nullable|boolean',
            'early_payment_discount_amount' => [
                'exclude_unless:has_early_payment_discount,true',
                'required',
                'numeric',
                'min:1',
                'max:100'
            ],
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id', // 'id' es correcto según tu lógica
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
            'products.*.customization_details.*.type' => 'required_with:products.*.customization_details|string',
            'products.*.customization_details.*.key' => 'required_with:products.*.customization_details|string',
            'products.*.customization_details.*.value' => 'required_with:products.*.customization_details|string',
        ]);

        $quote = null;

        // Usamos una transacción para asegurar la integridad de los datos.
        DB::transaction(function () use ($request, &$quote) { // Pasamos $quote por referencia
            // 1. Crear la cotización (v1)
            $quote = new Quote();
            $quote->user_id = auth()->id();
            $quote->branch_id = $request->branch_id;
            $quote->receiver = $request->receiver;
            $quote->department = $request->department;
            $quote->currency = $request->currency;
            $quote->tooling_cost = $request->tooling_cost;
            $quote->is_tooling_cost_stroked = $request->is_tooling_cost_stroked;
            $quote->freight_cost = $request->freight_cost;
            $quote->is_freight_cost_stroked = $request->is_freight_cost_stroked;
            $quote->freight_option = $request->freight_option;
            $quote->first_production_days = $request->first_production_days;
            $quote->notes = $request->notes;
            $quote->is_spanish_template = $request->is_spanish_template;
            $quote->show_breakdown = $request->show_breakdown;
            $quote->has_early_payment_discount = $request->has_early_payment_discount;
            $quote->early_payment_discount_amount = $request->early_payment_discount_amount ?? 0;
            
            // --- CAMPOS DE VERSIONADO (NUEVO) ---
            $quote->version = 1;
            $quote->is_active = true;
            $quote->status = 'Esperando respuesta'; // Status inicial

            $quote->save(); // Guardamos una vez para obtener el ID

            // 2. Asignar su ID como el root_quote_id
            $quote->root_quote_id = $quote->id;
            $quote->save(); // Guardamos de nuevo para setear el root_quote_id

            // 3. Adjuntar productos (Tu lógica, sin cambios)
            foreach ($request->products as $product) {
                $quote->products()->attach($product['id'], [
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'notes' => $product['notes'],
                    'show_image' => $product['show_image'],
                    'customization_details' => !empty($product['customization_details']) ? $product['customization_details'] : null,
                    'customer_approval_status' => 'Aprobado',
                ]);
            }
            
            // 4. Lógica de Notificación (Tu lógica, sin cambios)
            if ($quote) {
                $usersToNotify = User::permission('Autorizar ordenes de venta')->get();
    
                if ($usersToNotify->isNotEmpty()) {
                    Notification::send($usersToNotify, new NewQuoteForApprovalNotification($quote));
                }
            }
        });

        return Redirect::route('quotes.index')->with('success', 'Cotización (v1) creada exitosamente.');
    }

    public function show(Quote $quote)
    {
        // Cargar relaciones base de la cotización actual
        $quote->load(['branch', 'user', 'products.media', 'authorizedBy']);
        
        // Cargar TODAS las versiones de este hilo (para el dropdown)
        // Optimizamos seleccionando solo los campos necesarios para el selector
        $allVersions = $quote->allVersions()
                            ->select('id', 'version', 'created_at', 'root_quote_id', 'status') 
                            ->get();

        // Encontrar la posición de la cotización actual en la lista de versiones
        $currentIndex = $allVersions->search(function ($v) use ($quote) {
            return $v->id == $quote->id;
        });

        // Determinar ID de versión previa y siguiente (null si no existen)
        $prev_version_id = $allVersions->get($currentIndex - 1)?->id; // Null safe
        $next_version_id = $allVersions->get($currentIndex + 1)?->id; // Null safe

        // Obtener todas las cotizaciones ordenadas por ID
        $quotes = Quote::orderBy('id')->get();

        // Encontrar la posición de la cotización actual en la lista
        $currentIndex = $quotes->search(function ($q) use ($quote) {
            return $q->id == $quote->id;
        });
        
         // Obtener el ID de la siguiente cotización, manejando el caso en el que estamos en la última cotización
        $nextQuote = $quotes->get(($currentIndex + 1) % $quotes->count());

        // Obtener el ID de la cotización anterior, manejando el caso en el que estamos en la primera cotización
        $prevQuote = $quotes->get(($currentIndex - 1 + $quotes->count()) % $quotes->count());

        // Retornar la vista de Inertia
        return Inertia::render('Quote/Show', [
            'quote' => $quote, // La cotización completa que se está viendo
            'allVersions' => $allVersions, // La lista de todas las versiones
            'next_version_id' => $next_version_id,
            'prev_version_id' => $prev_version_id,
            'next_quote' => $nextQuote->id,
            'prev_quote' => $prevQuote->id,
        ]);
    }

    public function edit(Quote $quote)
    {
        // Obtenemos solo los productos de tipo 'Catálogo' para el selector principal.
        $catalogProducts = Product::where('product_type', 'Catálogo')->whereNull('archived_at')->select('id', 'name', 'code')->get();
        
        // Obtenemos todas las sucursales (clientes).
        $branches = Branch::select('id', 'name', 'status')->get();

        return Inertia::render('Quote/Edit', [
            'catalogProducts' => $catalogProducts,
            'branches' => $branches,
            'quote' => $quote,
        ]);
    }

    /**
     * Crea una NUEVA VERSIÓN de una cotización existente (Crea v2, v3...).
     *
     * @param Request $request
     * @param Quote $quote (Esta es la versión *anterior* que se está editando)
     */
    public function update(Request $request, Quote $quote)
    {
        // Tu validación (sin cambios)
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'receiver' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'tooling_cost' => 'nullable|string|min:0',
            'freight_cost' => 'required_unless:freight_option,El cliente manda la guia,Client sends the shipping label,Por cuenta del cliente,Paid by the client|nullable|numeric|min:0',
            'freight_option' => 'required|string',
            'first_production_days' => 'required|string',
            'has_early_payment_discount' => 'nullable|boolean',
            'early_payment_discount_amount' => [
                'exclude_unless:has_early_payment_discount,true',
                'required',
                'numeric',
                'min:1',
                'max:100'
            ],
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id', // 'id' es correcto según tu lógica
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
            'products.*.customization_details.*.type' => 'required_with:products.*.customization_details|string',
            'products.*.customization_details.*.key' => 'required_with:products.*.customization_details|string',
            'products.*.customization_details.*.value' => 'required_with:products.*.customization_details|string',
        ]);

        $newQuote = null;

        // Usamos una transacción para asegurar la integridad de los datos.
        DB::transaction(function () use ($request, $quote, &$newQuote) {
            
            // 1. Encontrar el ID raíz y la última versión
            $rootId = $quote->root_quote_id ?? $quote->id; // Si la v1 no tiene, usa su propio id
            $latestVersionNum = Quote::where('root_quote_id', $rootId)->max('version');

            // 2. Desactivar TODAS las versiones anteriores de este hilo
            Quote::where('root_quote_id', $rootId)->update(['is_active' => false]);

            // 3. Replicar (clonar) la cotización base
            $newQuote = $quote->replicate();

            // 4. Aplicar los nuevos datos del request a la cotización REPLICADA
            $newQuote->branch_id = $request->branch_id;
            $newQuote->receiver = $request->receiver;
            $newQuote->department = $request->department;
            $newQuote->currency = $request->currency;
            $newQuote->tooling_cost = $request->tooling_cost;
            $newQuote->is_tooling_cost_stroked = $request->is_tooling_cost_stroked;
            $newQuote->freight_cost = $request->freight_cost;
            $newQuote->is_freight_cost_stroked = $request->is_freight_cost_stroked;
            $newQuote->freight_option = $request->freight_option;
            $newQuote->first_production_days = $request->first_production_days;
            $newQuote->notes = $request->notes;
            $newQuote->is_spanish_template = $request->is_spanish_template;
            $newQuote->show_breakdown = $request->show_breakdown;
            $newQuote->has_early_payment_discount = $request->has_early_payment_discount;
            $newQuote->early_payment_discount_amount = $request->early_payment_discount_amount ?? 0;
            $newQuote->user_id = auth()->id(); // El autor de la NUEVA versión es el usuario actual

            // 5. Asignar los nuevos valores de versión
            $newQuote->version = $latestVersionNum + 1;
            $newQuote->is_active = true;
            $newQuote->root_quote_id = $rootId;
            
            // 6. Reiniciar campos de estado/respuesta
            $newQuote->status = 'Esperando respuesta'; // Reiniciar status
            $newQuote->sale_id = null; // Una nueva versión no está ligada a una venta anterior
            $newQuote->customer_responded_at = null;
            $newQuote->rejection_reason = null;
            $newQuote->authorized_at = null;
            $newQuote->authorized_by_user_id = null;
            $newQuote->created_at = now(); // `replicate` copia el created_at, lo reseteamos
            
            $newQuote->save(); // Guardar la nueva versión (v2, v3...)

            // 7. Adjuntar los productos a la NUEVA versión
            // (Tu misma lógica de attach, pero apuntando a $newQuote)
            foreach ($request->products as $product) {
                $newQuote->products()->attach($product['id'], [
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'notes' => $product['notes'],
                    'show_image' => $product['show_image'],
                    'customization_details' => !empty($product['customization_details']) ? $product['customization_details'] : null,
                    'customer_approval_status' => 'Aprobado',
                ]);
            }

            // 8. Lógica de Notificación (para la nueva versión)
            if ($newQuote) {
                $usersToNotify = User::permission('Autorizar ordenes de venta')->get();
    
                if ($usersToNotify->isNotEmpty()) {
                    Notification::send($usersToNotify, new NewQuoteForApprovalNotification($newQuote));
                }
            }
        });

        return Redirect::route('quotes.index')->with('success', 'Cotización actualizada. Se ha creado la versión ' . $newQuote->version);
    }

    public function destroy(Quote $quote)
    {
        //
    }

    public function massiveDelete(Request $request)
    {
        $affectedUserIds = [];

        // 1. Primero, recopilamos los IDs de los usuarios afectados antes de borrar
        foreach ($request->ids as $id) {
            $quote = Quote::find($id);
            if ($quote) {
                // Guardamos el ID del creador para revisarlo después
                $affectedUserIds[] = $quote->user_id;
                $quote->delete();
            }
        }

        // 2. Eliminamos duplicados para no revisar al mismo usuario varias veces
        $uniqueUserIds = array_unique($affectedUserIds);

        // 3. Ahora, revisamos las alertas para cada usuario afectado
        $threeDaysAgo = Carbon::now()->subDays(3);

        foreach ($uniqueUserIds as $userId) {
            $user = User::find($userId);
            if (!$user) {
                continue; // Si el usuario no existe, saltamos al siguiente
            }

            // Volvemos a buscar si este usuario AÚN tiene cotizaciones pendientes antiguas
            $remainingPendingQuotes = Quote::where('user_id', $user->id)
                ->where('status', 'Esperando respuesta')
                ->where('created_at', '<=', $threeDaysAgo)
                ->get();

            // Decidimos si actualizar o eliminar la alerta
            if ($remainingPendingQuotes->isNotEmpty()) {
                // Si todavía tiene, ACTUALIZAMOS la alerta con los IDs correctos
                $alertContent = [
                    'type' => 'pending_quotations',
                    'title' => 'Cotizaciones Pendientes',
                    'message' => 'Tienes ' . $remainingPendingQuotes->count() . ' cotización(es) sin respuesta por más de 3 días. Marca como aceptadas o rechazadas para quitar mensaje',
                    'quote_ids' => $remainingPendingQuotes->pluck('id')->toArray(),
                ];
                $user->addActiveAlert('pending_quotations', $alertContent);
            } else {
                // Si ya no tiene, ELIMINAMOS la alerta de su perfil
                $user->removeActiveAlert('pending_quotations');
            }
        }
        
        // Puedes retornar una respuesta si lo necesitas, por ejemplo, para notificar al frontend
        // return response()->json(['message' => 'Cotizaciones eliminadas y alertas actualizadas.']);
    }

    public function authorizeQuote(Quote $quote)
    {
        $quote->update([
            'authorized_by_user_id' => auth()->id(),
            'authorized_at' => now(),
        ]);

        // notificar a creador de la orden si quien autoriza no es el mismo usuario
        if (auth()->id() != $quote->user->id) {
            $quote_folio = 'COT-' . str_pad($quote->id, 4, "0", STR_PAD_LEFT);
            $quote->user->notify(new ApprovalQuoteNotification(
                'Cotización',
                $quote_folio,
                'quote',
                route('quotes.show', $quote->id)
            ));
        }

        return response()->json(['message' => 'Cotizacion autorizada', 'item' => $quote]); //en caso de actualizar en la misma vista descomentar
    }

    /**
     * Clona una cotización existente para crear una nueva.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clone(Quote $quote)
    {
        // // Validar que el ID de la cotización a clonar esté presente
        // $request->validate([
        //     'quote_id' => 'required|exists:quotes,id',
        // ]);

        try {
            // 1. Encontrar la cotización original con sus productos
            $originalQuote = Quote::with('products')->findOrFail($quote->id);

            // 2. Replicar el modelo de la cotización
            // El método replicate() crea una nueva instancia sin guardarla en la BD
            // y sin los atributos de fecha (created_at, updated_at) y el ID.
            $newQuote = $originalQuote->replicate();

            // 3. Restablecer y actualizar los campos para la nueva cotización
            $newQuote->status = 'Esperando respuesta'; // O el estado inicial que prefieras
            $newQuote->sale_id = null;
            $newQuote->authorized_at = null;
            $newQuote->authorized_by_user_id = null;
            $newQuote->customer_responded_at = null;
            $newQuote->rejection_reason = null;
            $newQuote->created_by_customer = false; // La nueva cotización es creada por un usuario del sistema
            $newQuote->user_id = auth()->id(); // Asignar el usuario actual como creador

            // 4. Guardar la nueva cotización para obtener un ID
            $newQuote->save();

            // 5. Sincronizar los productos de la cotización original a la nueva
            // Se itera sobre cada producto de la cotización original para adjuntarlo a la nueva
            // con sus respectivos datos pivote (cantidad, precio, etc.).
            foreach ($originalQuote->products as $product) {
                $newQuote->products()->attach($product->id, [
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'notes' => $product->pivot->notes,
                    'customization_details' => $product->pivot->customization_details,
                    'customer_approval_status' => $product->pivot->customer_approval_status,
                ]);
            }

            // Cargar las relaciones necesarias para la respuesta
            $newQuote->load('user', 'branch');

            // 6. Devolver una respuesta exitosa con la nueva cotización
            return response()->json([
                'message' => 'Cotización clonada exitosamente',
                'newItem' => $newQuote,
            ]);

        } catch (\Exception $e) {
            // Registrar el error para depuración
            Log::error('Error al clonar la cotización: ' . $e->getMessage());

            // Devolver una respuesta de error
            return response()->json([
                'message' => 'Ocurrió un error al clonar la cotización. Por favor, inténtalo de nuevo.',
            ], 500);
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $quotes = Quote::with(['branch', 'user', 'sale'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhereHas('user', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                // Correcto uso de whereHas para buscar en la relación
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $quotes], 200);
    }

    public function markEarlyPayment(Quote $quote) 
    {
        $quote->update([
            'early_paid_at' => now(),
        ]);

        return response()->json(['quote' => $quote]);
    }

    public function changeStatus(Quote $quote, Request $request)
    {
        $status = $request->input('new_status');
        $data = ['status' => $status];
        $rejection_reason = $request->input('rejection_reason');

        // Si es rechazada, guardar el motivo
        if ($status === 'Rechazada') {
            $data['rejection_reason'] = $rejection_reason;
            $data['customer_responded_at'] = now();
        }

        // Si es aceptada, guardar también quién la autorizó
        if ($status === 'Aceptada') {
            $data['customer_responded_at'] = now();
        }

        $quote->update($data);

        // -------------- LÓGICA DE ACTUALIZACIÓN DE ALERTAS EN TIEMPO REAL --------------

        // 1. Obtener el usuario que creó la cotización
        $user = $quote->user;

        // 2. Volver a buscar si este usuario AÚN tiene cotizaciones pendientes antiguas
        $threeDaysAgo = Carbon::now()->subDays(3);
        $remainingPendingQuotes = Quote::where('user_id', $user->id)
            ->where('status', 'Esperando respuesta')
            ->where('is_active', true)
            ->where('created_at', '<=', $threeDaysAgo)
            ->get();

        // 3. Decidir si actualizar o eliminar la alerta
        if ($remainingPendingQuotes->isNotEmpty()) {
            // Si todavía tiene, ACTUALIZAMOS la alerta con los IDs correctos
            $alertContent = [
                'type' => 'pending_quotations',
                'title' => 'Cotizaciones Pendientes',
                'message' => 'Tienes ' . $remainingPendingQuotes->count() . ' cotización(es) sin respuesta por más de 3 días. Marca como aceptadas o rechazadas para quitar mensaje',
                'quote_ids' => $remainingPendingQuotes->pluck('id')->toArray(),
            ];
            $user->addActiveAlert('pending_quotations', $alertContent);
        } else {
            // Si ya no tiene, ELIMINAMOS la alerta de su perfil
            $user->removeActiveAlert('pending_quotations');
        }
        // -------------- FIN DE LA LÓGICA DE ALERTAS --------------


        return response()->json([
            'message' => 'Estatus de la cotización actualizado correctamente.',
            'quote' => $quote
        ]);
    }

    // Recupera todas las cotizaciones de una sucursal
    // Uso el metodo en el show de clientes para la pestaña de cotizaciones.
    public function fetchBranchQuotes(Branch $branch)
    {
        $quotes = Quote::with('user:id,name')->where('branch_id', $branch->id)
            ->latest()
            ->take(20) // Limita a las 20 más recientes
            ->get(['id', 'user_id', 'authorized_at', 'sale_id', 'status', 'created_at', 'branch_id', 'currency', 'has_early_payment_discount', 
            'early_paid_at', 'customer_responded_at']);

        return response()->json($quotes);
    }

    /**
     * Recupera los detalles de una cotización para ser usados en la creación de una venta.
     *
     * @param  \App\Models\Quote  $quote
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailsForSale(Quote $quote)
    {
        // MODIFICADO: Cargamos también la relación 'media' de cada producto.
        $quote->load('branch', 'products.media');

        // Formateamos los productos para que sea más fácil consumirlos en el frontend.
        // Solo incluimos los productos que fueron aprobados en la cotización.
        $approvedProducts = $quote->products
            ->where('pivot.customer_approval_status', 'Aprobado')
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'cost' => $product->cost,
                    'quantity' => $product->pivot->quantity,
                    'unit_price' => $product->pivot->unit_price,
                    'customization_details' => $product->pivot->customization_details,
                    'notes' => $product->pivot->notes,
                    // NUEVO: Agregamos la URL de la primera imagen del producto.
                    'image_url' => $product->media->first()?->original_url,
                ];
            });

        return response()->json([
            'branch_id' => $quote->branch_id,
            'contact_id' => $quote->branch->main_contact_id,
            'freight_option' => $quote->freight_option,
            'freight_cost' => $quote->freight_cost,
            'notes' => $quote->notes,
            'currency' => $quote->currency,
            'products' => $approvedProducts->values(),
        ]);
    }

    // Cambia el estatus de los productos den la cotizacion (Aprobado/Pendiente)
    public function updateProductStatus(Request $request, QuoteProduct $quoteProduct)
    {
        $request->validate([
            'status' => 'required|in:Pendiente,Aprobado,Rechazado',
        ]);

        $quoteProduct->update(['customer_approval_status' => $request->status]);

        // Opcional: Recalcular totales de la cotización aquí si es necesario.

        return response()->json(['message' => 'Estatus del producto actualizado con éxito.']);
    }
}
