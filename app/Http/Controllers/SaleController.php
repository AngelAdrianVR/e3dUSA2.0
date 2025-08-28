<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        // Determina si se deben mostrar todas las ventas o solo las del usuario.
        // El frontend enviará un query param `view=all` para ver todas.
        $showAll = $request->query('view') === 'all';

        // Aquí deberías agregar tu lógica de permisos.
        // Ejemplo: if ($showAll && !Auth::user()->can('view all sales')) {
        //     abort(403);
        // }

        $query = Sale::query();

        // Si no se solicita ver todo, filtra por el usuario autenticado.
        if (!$showAll) {
            $query->where('user_id', Auth::id());
        }

        // Carga ansiosa (Eager Loading) para evitar problemas N+1 en la vista.
        // Cargamos las relaciones 'user' (creador) y 'branch' (cliente).
        $sales = $query->with(['user', 'branch'])
                       ->latest() // Ordena por los más recientes primero
                       ->paginate(15) // Pagina los resultados
                       ->withQueryString(); // Mantiene los query params (ej. `view=all`) en la paginación

        // Retorna la vista de Inertia con los datos paginados.
        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['view']), // Pasa los filtros actuales a la vista
        ]);
    }

    public function create()
    {
        // Obtenemos todas las sucursales (clientes) activas.
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Obtenemos solo las cotizaciones que han sido autorizadas y no están en una OV.
        $quotes = Quote::where('authorized_at', '!=', null)
                    ->latest()
                    ->whereDoesntHave('sale')
                    ->select('id', 'branch_id')
                    ->with('branch:id,name')
                    ->get();
                    //    return $branches;
        return Inertia::render('Sale/Create', [
            'branches' => $branches,
            'quotes' => $quotes,
            'catalog_products' => Product::where('product_type', 'Catálogo')->select('id', 'name')->get()
        ]);
    }

    public function store(Request $request)
    {
        //
    }

    public function show(Sale $sale)
    {
        //
    }

    public function edit(Sale $sale)
    {
        //
    }

    public function update(Request $request, Sale $sale)
    {
        //
    }

    public function destroy(Sale $sale)
    {
        //
    }
}
