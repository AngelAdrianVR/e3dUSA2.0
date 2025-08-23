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
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

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
            'currency' => [
                'nullable',
                'required_if:product_type_key,C',
                'string',
            ],
            'caracteristics' => 'nullable|string',
            'cost' => 'nullable|numeric|max:99999',
            'base_price' => [
                'nullable',
                'required_if:product_type_key,C',
                'numeric',
                'min:0',
            ],
            'brand_id' => 'required|exists:brands,id',
            'product_type_key' => 'required|string|in:C,MP,I',
            'product_family_id' => 'required|exists:product_families,id',
            'material' => 'required|string',
            'measure_unit' => 'nullable|string|max:100',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0|gte:min_quantity',
            'is_circular' => 'nullable|boolean',
            'width' => 'nullable|numeric|min:0',
            'large' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'diameter' => 'nullable|numeric|min:0',
            'media' => 'nullable|array|max:3',
            'media.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'components' => [
                'nullable',
                'array',
                Rule::when(fn ($input) => $input->hasComponents === true, ['min:2']),
            ],
            'components.*.product_id' => 'required_with:components|exists:products,id',
            'components.*.quantity' => 'required_with:components|numeric|min:1',
            'production_processes' => 'nullable|array',
            'production_processes.*.process_id' => 'required_with:production_processes|exists:production_costs,id',
        ]);

        // 2. MAPEO DE CLAVES (sin cambios)
        $productTypes = ['C' => 'Catálogo', 'MP' => 'Materia Prima', 'I' => 'Insumo'];
        $materials = ['M' => 'Metal', 'PL' => 'Piel de lujo', 'O' => 'Original', 'L' => 'Lujo', 'P' => 'Piel', 'PLS' => 'Plastico', 'ZK' => 'Zamak',
                    'SCH' => 'Solid Chrome', 'MM' => 'Micrometal'];

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
            $product = Product::create(
                $validatedData 
                + [
                    'base_price_updated_at' => now()
                ]
            );

            // --- Crear registro de inventario inicial ---
            $product->storages()->create([
                'quantity' => $request->current_stock ?? 0,
                'location' => $request->location // O puedes dejarlo null si tu lógica lo permite
            ]);

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

    public function show(Product $catalog_product): Response
    {
        // Carga todas las relaciones necesarias para la vista de detalles.
        // Usar load() es eficiente porque solo carga las relaciones para este producto específico.
        $catalog_product->load([
            'media', // Para la galería de imágenes
            'brand', // Para obtener el nombre de la marca
            'productFamily', // Para obtener el nombre de la familia
            'storages.stockMovements', // Para existencias, ubicación y movimientos de stock
            'components.media', // Materia prima que compone el producto
            'productionCosts', // Procesos de producción asociados
            'priceHistory.branch', // historial de precios
        ]);

        // Obtiene una lista de todos los productos para el buscador/selector.
        // Solo seleccionamos 'id' y 'name' para que la consulta sea ligera.
        $all_products = Product::query()
            ->select('id', 'name')
            ->where('product_type', 'Catálogo') // Asumiendo que solo quieres productos de catálogo en el selector
            ->orderBy('name')
            ->get();

        // Renderiza la vista de Inertia y le pasa los datos necesarios.
        return Inertia::render('CatalogProduct/Show', [
            // Pasamos el producto con todas sus relaciones ya cargadas.
            // Lo envolvemos en 'data' para que coincida con la estructura que espera el prop.
            'product' => $catalog_product,
            // Pasamos la lista completa de productos para el selector.
            'catalog_products' => $all_products,
        ]);
    }

    public function edit(Product $catalog_product)
    {
        // Carga todas las relaciones necesarias para el formulario
        $catalog_product->load('brand', 'productFamily', 'storages', 'components', 'productionCosts', 'media');

        // Aquí deberías pasar los mismos datos que en tu método `create`
        // para poblar los selectores (marcas, familias, etc.)
        return inertia('CatalogProduct/Edit', [
            'catalog_product' => $catalog_product,
            'brands' => Brand::all(),
            'product_families' => ProductFamily::all(),
            'production_processes' => ProductionCost::all(),
            'raw_materials' => Product::where('product_type', 'Materia prima')->get(),
        ]);
    }

    public function update(Request $request, Product $catalog_product)
    {
        // 1. REGLAS DE VALIDACIÓN
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            // Ignora el producto actual al verificar si el código es único
            'code' => ['required', 'string', Rule::unique('products')->ignore($catalog_product->id)],
            'caracteristics' => 'nullable|string',
            'cost' => 'nullable|numeric|max:99999',
            'currency' => [
                'nullable',
                'required_if:product_type_key,C',
                'string',
            ],
            'base_price' => [
                'nullable',
                'required_if:product_type_key,C',
                'numeric',
                'min:0',
            ],
            'brand_id' => 'required|exists:brands,id',
            'product_type_key' => 'required|string|in:C,MP,I',
            'product_family_id' => 'required|exists:product_families,id',
            'material' => 'required|string',
            'measure_unit' => 'nullable|string|max:100',
            'min_quantity' => 'nullable|numeric|min:0',
            'max_quantity' => 'nullable|numeric|min:0|gte:min_quantity',
            'is_circular' => 'nullable|boolean',
            'width' => 'nullable|numeric|min:0',
            'large' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'diameter' => 'nullable|numeric|min:0',
            'media' => 'nullable|array|max:3',
            'media.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'components' => [
                'nullable',
                'array',
                Rule::when(fn ($input) => $input->hasComponent === true, ['min:2']),
            ],
            'components.*.product_id' => 'required_with:components|exists:products,id',
            'components.*.quantity' => 'required_with:components|numeric|min:1',
            'production_processes' => 'nullable|array',
            'production_processes.*.process_id' => 'required_with:production_processes|exists:production_costs,id',
            'location' => 'nullable|string|max:255',
        ]);

        // 2. MAPEO DE CLAVES
        $productTypes = ['C' => 'Catálogo', 'MP' => 'Materia Prima', 'I' => 'Insumo'];
        $materials = ['M' => 'Metal', 'PL' => 'Piel de lujo', 'O' => 'Original', 'L' => 'Lujo', 'P' => 'Piel', 'PLS' => 'Plastico', 'ZK' => 'Zamak', 'SCH' => 'Solid Chrome', 'MM' => 'Micrometal'];

        $validatedData['product_type'] = $productTypes[$validatedData['product_type_key']];
        $validatedData['material'] = $materials[$validatedData['material']];

        // 3. LÓGICA DE CÁLCULO DE COSTO
        $totalProcessesCost = 0;
        if ($request->filled('production_processes')) {
            $processIds = array_column($request->input('production_processes'), 'process_id');
            $processes = ProductionCost::whereIn('id', $processIds)->get();
            $totalProcessesCost = $processes->sum('cost');
        }
        $initialCost = is_numeric($validatedData['cost']) ? $validatedData['cost'] : 0;
        $validatedData['cost'] = $initialCost + $totalProcessesCost;

        // 4. TRANSACCIÓN DE BASE DE DATOS
        DB::beginTransaction();
        try {
            // Actualiza el producto principal
            $catalog_product->update($validatedData);

            // Actualiza el registro de inventario
            $storage = $catalog_product->storages()->first();
            if ($storage) {
                $storage->update(['location' => $request->location]);
            }

            // 5. SINCRONIZAR RELACIONES (si es un producto de catálogo)
            if ($validatedData['product_type_key'] === 'C') {
                $componentsToSync = [];
                if ($request->filled('components')) {
                    foreach ($request->input('components') as $component) {
                        $componentsToSync[$component['product_id']] = ['quantity' => $component['quantity']];
                    }
                }
                $catalog_product->components()->sync($componentsToSync); // sync() elimina los que no están y agrega los nuevos

                $processesToSync = [];
                if ($request->filled('production_processes')) {
                    foreach ($request->input('production_processes') as $index => $process) {
                        $processesToSync[$process['process_id']] = ['order' => $index + 1];
                    }
                }
                $catalog_product->productionCosts()->sync($processesToSync);
            }

            // 6. MANEJO DE IMÁGENES NUEVAS
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $catalog_product->addMedia($file)->toMediaCollection('images');
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->withErrors(['message' => 'Ocurrió un error inesperado al actualizar el producto: ' . $e->getMessage()]);
        }

        // 7. REDIRECCIÓN CON MENSAJE DE ÉXITO
        return to_route('catalog-products.show', $catalog_product->id);
    }

    public function destroy(Product $product)
    {
        $product->delete();
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
        $product->load(['media', 'storages']);

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
        $baseCode = $request->input('base_code');

        // Busca productos donde el campo 'code' comience con el código base proporcionado
        // y carga la relación 'media' para poder mostrar la imagen en el frontend.
        $similarProducts = Product::where('code', 'LIKE', $baseCode . '-%')
            ->with('media') // Carga ansiosa de la relación de medios
            ->get(['id', 'name', 'code', 'caracteristics']); // Selecciona solo los campos necesarios

        return response()->json($similarProducts);
    }

    /**
     * Recupera todos los productos del tipo especificado en la peticion de la vista.
     *
     * *Lo utilizo en el create de cotizaciones para actualizar la lista si reiniciar el formulario
     * 
     */
    public function fetchProducts(Request $request)
    {   
        // Obtiene el parámetro product_type que viene del frontend
        $productType = $request->params['product_type'];
        
        // Filtra los productos según el tipo
        $products = Product::where('product_type', $productType)
            ->get(['id', 'name', 'code']);

        // Retorna en JSON
        return response()->json($products);
    }

    public function handleStockMovement(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:Entrada,Salida',
            'notes' => 'nullable|string|max:500',
        ]);

        // Asumimos que el producto tiene al menos un almacén, como en tu vista de Vue.
        $storage = $product->storages()->first();
        
        if (!$storage) {
            return back()->withErrors(['message' => 'El producto no tiene un almacén asociado.']);
        }
        
        // Validar que haya stock suficiente para una salida
        if ($validated['type'] === 'Salida' && $storage->quantity < $validated['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => 'No hay existencias suficientes para realizar la salida. Stock actual: ' . $storage->quantity,
            ]);
        }
            $quantityChange = $validated['quantity'];

            if ($validated['type'] === 'Entrada') {
                $storage->increment('quantity', $quantityChange);
            } else {
                // Para salidas, decrementamos
                $storage->decrement('quantity', $quantityChange);
                // El cambio se guarda como un número positivo, el 'type' indica la operación
            }

            // Registrar el movimiento en la nueva tabla
            $storage->stockMovements()->create([
                'product_id' => $product->id,
                'quantity_change' => $quantityChange,
                'type' => $validated['type'],
                'notes' => $validated['notes'],
            ]);
        // return redirect()->back();
    }

    // Marca como obsoleto el producto agregando fecha a archived_at
    public function markAsObsolet(Product $product)
    {
        if ( $product->archived_at ) {
            $product->update([
                'archived_at' => null
            ]);
        } else {
            $product->update([
                'archived_at' => now()
            ]);
        }

        return response()->json($product->archived_at);
    }

    // Actualiza el precio base desde el show de producto de catalogo
    // modificarlo si se requiere actualizar otra variable por ahora solo tiene base:price
    public function simpleUpdate(Request $request, Product $product)
    {
        $request->validate([
            'base_price' => 'required|numeric|min:0',
        ]);

        $product->update([
            'base_price' => $request->base_price,
            'base_price_updated_at' => now(),
        ]);

        return response()->json([
            'base_price' => $product->base_price,
            'base_price_updated_at' => $product->base_price_updated_at,
        ]);
    }

}
