<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class ProductController extends Controller
{
    public function index()
    {
        $catalog_products = Product::with('media')
            ->where('product_type', 'Catalogo')
            ->with('brand:id,name')
            ->latest()
            ->select(['id', 'code', 'name', 'cost', 'material','brand_id'])
            ->paginate(30);

        return inertia('CatalogProduct/Index', compact('catalog_products'));
    }

    public function create()
    {
        // 1. Obtener el último ID de la tabla de productos para calcular el consecutivo.
        // Se usa `latest('id')->first()` para eficiencia. Si no hay productos, el ID base será 0.
        $lastProduct = Product::latest('id')->first();
        $consecutive = $lastProduct ? $lastProduct->id + 1 : 1;

        // 2. Cargar las colecciones necesarias para los selectores del formulario.
        // Se obtienen los más recientes primero.
        $brands = Brand::latest()->get();
        $product_families = ProductFamily::get();

        // 3. Renderizar la vista de Vue (Inertia) y pasarle los datos como props.
        return inertia('CatalogProduct/Create', [
            'brands' => $brands,
            'product_families' => $product_families,
            'consecutive' => $consecutive,
        ]);
    }

    public function store(Request $request)
    {
        // 1. REGLAS DE VALIDACIÓN
        // Para un proyecto más grande, se recomienda mover esto a un Form Request (ej. StoreProductRequest).
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:products,code',
            'caracteristics' => 'nullable|string',
            'cost' => 'nullable|numeric|max:99999',
            'brand_id' => 'required|exists:brands,id',
            'product_type_key' => 'required|string|in:C,MP,I',
            'product_family_id' => 'required|exists:product_families,id',
            'material' => 'required|string',
            'measure_unit' => 'nullable|string|max:100',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0|gte:min_quantity',
            'is_circular' => 'nullable|boolean',
            'width' => 'required|numeric|min:0',
            'large' => 'nullable|required_if:is_circular,false|numeric|min:0',
            'height' => 'nullable|required_if:is_circular,false|numeric|min:0',
            'diameter' => 'nullable|required_if:is_circular,true|numeric|min:0',
        ]);

        // 2. MAPEO DE CLAVES A VALORES COMPLETOS
        // Esto permite tener una DB más legible.
        $productTypes = ['C' => 'Catálogo', 'MP' => 'Materia Prima', 'I' => 'Insumo'];
        $materials = ['M' => 'Metal', 'PL' => 'Piel de lujo', 'O' => 'Original', 'L' => 'Lujo', 'P' => 'Piel', 'PLS' => 'Plastico', 'ZK' => 'Zamak'];

        $validatedData['product_type'] = $productTypes[$validatedData['product_type_key']];
        $validatedData['material'] = $materials[$validatedData['material']];

        // 3. TRANSACCIÓN DE BASE DE DATOS
        // Asegura que si algo falla, nada se guarde.
        try {
            DB::beginTransaction();

            // Crea el producto con los datos validados
            $product = Product::create($validatedData);

            // 4. MANEJO DE IMÁGENES (usando spatie/laravel-medialibrary)
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    // Agrega cada archivo a la colección 'images' del producto.
                    $product->addMedia($file)->toMediaCollection('images');
                }
            }

            DB::commit(); // Todo salió bien, confirma los cambios.

        } catch (\Exception $e) {
            DB::rollBack(); // Algo falló, revierte todo.

            // Redirige hacia atrás con un mensaje de error.
            return Redirect::back()->with('error', 'Ocurrió un error inesperado al guardar el producto: ' . $e->getMessage());
        }

        // 5. REDIRECCIÓN AL INDEX
        return to_route('catalog-products.index');
    }
    public function show(Product $product)
    {
        //
    }

    public function edit(Product $product)
    {
        //
    }

    public function update(Request $request, Product $product)
    {
        //
    }

    public function destroy(Product $product)
    {
        //
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Product::find($id);
            $bonus?->delete();
        }
    }

    // Busca y retorna los porductos del tipo materia prima para componentes de producto de catalogo
    // SE USA EN: create, edit de productos.
    public function fetchRawMaterials(Request $request)
    {
        $query = $request->input('query');

        $rawMaterials = Product::where('product_type', 'Materia prima') // O la clave que uses, ej. 'MP'
                            ->where('name', 'LIKE', "%{$query}%")
                            ->select('id', 'name') // Solo selecciona los datos necesarios
                            ->limit(10) // Limita los resultados para no sobrecargar
                            ->get();

        return response()->json($rawMaterials);
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $catalog_products = Product::with('media')
            ->latest()
            ->select(['id', 'brand_id', 'code', 'name', 'cost', 'material']) // Es buena idea incluir el brand_id
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->orWhere('material', 'like', "%{$query}%")
                // Correcto uso de whereHas para buscar en la relación
                ->orWhereHas('brand', function ($brandQuery) use ($query) {
                    $brandQuery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $catalog_products], 200);
    }
}
