<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Notifications\ApprovalQuoteNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class QuoteController extends Controller
{
    public function index()
    {
        // Se obtienen las cotizaciones paginadas.
        // Usamos 'with' para cargar las relaciones y evitar el problema N+1.
        // Esto hace que la consulta sea mucho más eficiente.
        $quotes = Quote::with(['branch', 'user', 'sale'])
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
}
