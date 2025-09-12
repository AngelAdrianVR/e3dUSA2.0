<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorageController extends Controller
{
    /**
     * Muestra la vista principal del almacén, filtrando los productos
     * según el tipo seleccionado por el usuario.
     */
    public function index(Request $request)
    {
        $productType = $request->input('product_type', 'Catálogo'); // 'Catálogo' como valor por defecto
        $searchTerm = $request->input('search');

        // Iniciar la consulta base, incluyendo siempre las relaciones y campos necesarios
        $query = Product::query()
            ->with(['storages:id,storable_id,storable_type,quantity,location', 'brand:id,name', 'media']);

        // Aplicar filtro por tipo de producto
        if ($productType === 'Obsoleto') {
            $query->obsolete(); // Usa el scope para productos archivados
        } else {
            $query->active()->ofType($productType); // Usa scopes para activos y por tipo
        }

        // Aplicar filtro de búsqueda si existe
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('code', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('brand', function ($brandQuery) use ($searchTerm) {
                      $brandQuery->where('name', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Paginar los resultados
        $products = $query->latest('id')->paginate(15)->withQueryString();

        // Devolver la vista de Inertia con los datos
        return Inertia::render('Warehouse/Index', [
            'products' => $products,
            'filters' => $request->only(['product_type', 'search']),
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        //
    }

    public function show(Storage $storage)
    {
        //
    }

    public function edit(Storage $storage)
    {
        //
    }

    public function update(Request $request, Storage $storage)
    {
        //
    }

    public function destroy(Storage $storage)
    {
        //
    }
}
