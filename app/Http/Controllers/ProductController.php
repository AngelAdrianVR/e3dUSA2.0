<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductionCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Http\JsonResponse;

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
        $brands = Brand::get();
        $product_families = ProductFamily::get();
        $production_processes = ProductionCost::where('is_active', true)->get(['id', 'name', 'cost', 'estimated_time_seconds']);
        $raw_materials = Product::where('product_type', 'Materia prima')->get(['id', 'name']);

        // 3. Renderizar la vista de Vue (Inertia) y pasarle los datos como props.
        return inertia('CatalogProduct/Create', [
            'brands' => $brands,
            'product_families' => $product_families,
            'production_processes' => $production_processes,
            'raw_materials' => $raw_materials,
            'consecutive' => $consecutive,
        ]);
    }

    public function store(Request $request)
    {
        // 1. REGLAS DE VALIDACIÓN (sin cambios)
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:products,code',
            'caracteristics' => 'nullable|string',
            'cost' => 'nullable|numeric|max:99999',
            'base_price' => 'nullable|numeric|min:0',
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
            'media' => 'nullable|array|max:3',
            'media.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'components' => 'nullable|array',
            'components.*.product_id' => 'required_with:components|exists:products,id',
            'components.*.quantity' => 'required_with:components|numeric|min:1',
            'production_processes' => 'nullable|array',
            'production_processes.*.process_id' => 'required_with:production_processes|exists:production_costs,id',
        ]);

        // 2. MAPEO DE CLAVES (sin cambios)
        $productTypes = ['C' => 'Catálogo', 'MP' => 'Materia Prima', 'I' => 'Insumo'];
        $materials = ['M' => 'Metal', 'PL' => 'Piel de lujo', 'O' => 'Original', 'L' => 'Lujo', 'P' => 'Piel', 'PLS' => 'Plastico', 'ZK' => 'Zamak'];

        $validatedData['product_type'] = $productTypes[$validatedData['product_type_key']];
        $validatedData['material'] = $materials[$validatedData['material']];

        // ==================================================================
        // ================== INICIO: LÓGICA DE CÁLCULO DE COSTO ============
        // ==================================================================
        $totalProcessesCost = 0;
        if ($request->filled('production_processes')) {
            // Obtenemos todos los IDs de los procesos que vienen en el request
            $processIds = array_column($request->input('production_processes'), 'process_id');

            // Consultamos la base de datos UNA SOLA VEZ para obtener los costos reales de esos procesos.
            // Esto es más seguro y eficiente que confiar en los datos del frontend.
            $processes = ProductionCost::whereIn('id', $processIds)->get();

            // Sumamos el costo de cada proceso encontrado
            $totalProcessesCost = $processes->sum('cost');
        }

        // Nos aseguramos de que el costo inicial sea un número (0 si es nulo)
        $initialCost = is_numeric($validatedData['cost']) ? $validatedData['cost'] : 0;

        // Sumamos el costo de los procesos al costo inicial del producto
        $validatedData['cost'] = $initialCost + $totalProcessesCost;
        // ================================================================
        // ================== FIN: LÓGICA DE CÁLCULO DE COSTO ===============
        // ================================================================


        // 3. TRANSACCIÓN DE BASE DE DATOS
        DB::beginTransaction();
        try {
            // Crea el producto principal con el costo ya actualizado
            $product = Product::create($validatedData);

            // 4. GUARDAR RELACIONES (si es un producto de catálogo)
            if ($validatedData['product_type_key'] === 'C') {
                if ($request->filled('components')) {
                    $componentsToSync = [];
                    foreach ($request->input('components') as $component) {
                        $componentsToSync[$component['product_id']] = ['quantity' => $component['quantity']];
                    }
                    $product->components()->sync($componentsToSync);
                }

                if ($request->filled('production_processes')) {
                    $processesToSync = [];
                    foreach ($request->input('production_processes') as $index => $process) {
                        $processesToSync[$process['process_id']] = ['order' => $index + 1];
                    }
                    $product->productionCosts()->sync($processesToSync);
                }
            }

            // 5. MANEJO DE IMÁGENES (sin cambios)
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $product->addMedia($file)->toMediaCollection('images');
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            // \Log::error('Error al crear producto: ' . $e->getMessage()); // Descomenta para depurar
            return Redirect::back()->with('error', 'Ocurrió un error inesperado al guardar el producto. Por favor, inténtelo de nuevo.');
        }

        // 6. REDIRECCIÓN CON MENSAJE DE ÉXITO
        return to_route('catalog-products.index')->with('success', 'Producto creado con éxito.');
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

    // Busca y retorna los porductos del tipo materia prima que coincidan con lo escrito para componentes de producto de catalogo
    // SE USA EN: create, edit de productos.
    public function searchRawMaterial(Request $request)
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

    public function getProductMedia(Product $product)
    {
        $product->load('media');

        return response()->json(compact('product'));
    }

    /**
     * Busca productos con un código base similar.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function findSimilar(Request $request): JsonResponse
    {
        // $request->validate([
        //     'base_code' => 'required|string',
        // ]);
        $baseCode = $request->input('base_code');

        // Busca productos donde el campo 'code' comience con el código base proporcionado
        // y carga la relación 'media' para poder mostrar la imagen en el frontend.
        $similarProducts = Product::where('code', 'LIKE', $baseCode . '-%')
            ->with('media') // Carga ansiosa de la relación de medios
            ->get(['id', 'name', 'code', 'caracteristics']); // Selecciona solo los campos necesarios

        return response()->json($similarProducts);
    }
}
