<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
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
        //
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
}
