<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\ApprovalQuoteNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;

class QuoteController extends Controller
{
    public function index()
    {
        // Se obtienen las cotizaciones paginadas.
        // Usamos 'with' para cargar las relaciones y evitar el problema N+1.
        // Esto hace que la consulta sea mucho más eficiente.
        $quotes = Quote::with(['branch', 'user', 'sale', 'authorizedBy'])
            ->latest() // Ordena por los más recientes primero
            ->paginate(20); // O el número que prefieras por página

        // Retornamos la vista de Inertia, pasando los datos de las cotizaciones.
        return Inertia::render('Quote/Index', [
            'quotes' => $quotes,
        ]);
    }

    public function create()
    {
        // Obtenemos solo los productos de tipo 'Catálogo' para el selector principal.
        $catalogProducts = Product::where('product_type', 'Catálogo')->select('id', 'name', 'code')->get();
        
        // Obtenemos todas las sucursales (clientes).
        $branches = Branch::select('id', 'name', 'status')->get();

        return Inertia::render('Quote/Create', [
            'catalogProducts' => $catalogProducts,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        // Validación de los datos principales de la cotización.
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'receiver' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'currency' => 'required|string|max:3',
            'tooling_cost' => 'required|numeric|min:0',
            'freight_cost' => 'required|numeric|min:0',
            'freight_option' => 'required|string',
            'first_production_days' => 'required|string',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Usamos una transacción para asegurar la integridad de los datos.
        DB::transaction(function () use ($request) {
            // Crear la cotización con los datos validados.
            $quote = Quote::create([
                'user_id' => auth()->id(),
                'branch_id' => $request->branch_id,
                'receiver' => $request->receiver,
                'department' => $request->department,
                'currency' => $request->currency,
                'tooling_cost' => $request->tooling_cost,
                'is_tooling_cost_stroked' => $request->is_tooling_cost_stroked,
                'freight_cost' => $request->freight_cost,
                'is_freight_cost_stroked' => $request->is_freight_cost_stroked,
                'freight_option' => $request->freight_option,
                'first_production_days' => $request->first_production_days,
                'notes' => $request->notes,
                'is_spanish_template' => $request->is_spanish_template,
                'show_breakdown' => $request->show_breakdown,
                'has_early_payment_discount' => $request->has_early_payment_discount,
                'early_payment_discount_amount' => $request->early_payment_discount_amount ?? 0,
            ]);

            // Preparar los datos de los productos para la tabla pivote.
            $productsData = [];
            foreach ($request->products as $product) {
                $productsData[$product['id']] = [
                    'quantity' => $product['quantity'],
                    'unit_price' => $product['unit_price'],
                    'notes' => $product['notes'],
                    'customization_details' => $product['customization_details'],
                    'customer_approval_status' => 'Aprobado', // Por defecto se aprueban al crear
                ];
            }

            // Adjuntar los productos a la cotización con sus datos pivote.
            $quote->products()->attach($productsData);
        });

        return Redirect::route('quotes.index')->with('success', 'Cotización creada exitosamente.');
    }

    public function show(Quote $quote)
    {
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

        // Preparar los recursos de la cotización actual
        $quote = Quote::with(['branch', 'user', 'products.media', 'authorizedBy'])->findOrFail($quote->id);

 // // Cargar las relaciones necesarias para evitar problemas N+1
        // $quote->load([
        //     'branch', // Cliente (sucursal)
        //     'user',   // Usuario que creó la cotización
        //     'products' // Los productos pivote y la información del producto real
        // ]);
        // return $quote;
        // Retornar la vista de Inertia con los datos de la cotización
        return Inertia::render('Quote/Show', [
            'quote' => $quote,
            'next_quote' => $nextQuote->id,
            'prev_quote' => $prevQuote->id,
        ]);
    }

    public function edit(Quote $quote)
    {
        //
    }

    public function update(Request $request, Quote $quote)
    {
        //
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
                    'message' => 'Tienes ' . $remainingPendingQuotes->count() . ' cotización(es) sin respuesta por más de 3 días.',
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
                // Busca dentro de la relación de la matriz (parent)
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
            ->where('created_at', '<=', $threeDaysAgo)
            ->get();

        // 3. Decidir si actualizar o eliminar la alerta
        if ($remainingPendingQuotes->isNotEmpty()) {
            // Si todavía tiene, ACTUALIZAMOS la alerta con los IDs correctos
            $alertContent = [
                'type' => 'pending_quotations',
                'title' => 'Cotizaciones Pendientes',
                'message' => 'Tienes ' . $remainingPendingQuotes->count() . ' cotización(es) sin respuesta por más de 3 días.',
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
        $quotes = Quote::with('user:id,name')->where('branch_id', $branch->id)->get();

        return response()->json($quotes);
    }
}
