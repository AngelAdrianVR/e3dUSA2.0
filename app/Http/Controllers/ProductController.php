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
use App\Exports\CatalogProductPricesExport;
use App\Exports\CatalogProductPricesExportABC;
use App\Exports\CatalogProductPricesExportPriceABC;
use App\Models\Branch;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // 'Producto' como valor por defecto en lugar de 'Catálogo'
        $productType = $request->input('product_type', 'Producto'); 
        $familyId = $request->input('family_id');
        $searchTerm = $request->input('search');

        // Iniciar la consulta base para el index (sin baseProducts() todavía)
        $query = Product::query()
            ->with([
                'storages:id,storable_id,storable_type,quantity,location', 
                'brand:id,name', 
                'media'
            ]);

        if ($productType === 'Obsoleto') {
            // NO usamos baseProducts() para incluir variantes que fueron marcadas como obsoletas de forma individual.
            $query->obsolete()
                  ->with(['variants' => function ($q) {
                      $q->with(['media', 'storages', 'brand']);
                  }]); 
        } else {
            // Usamos baseProducts() para listar solo los padres en la tabla principal,
            // y en la relación 'variants' aplicamos active() para que NO devuelva las variantes obsoletas.
            $query->baseProducts()
                  ->active()
                  ->ofType($productType)
                  ->with(['variants' => function ($q) {
                      $q->active()->with(['media', 'storages', 'brand']);
                  }]); 
        }

        if ($familyId) {
            $query->where('product_family_id', $familyId);
        }

        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('code', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('id', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('material', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('brand', function ($brandQuery) use ($searchTerm) {
                      $brandQuery->where('name', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        $products = $query->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('CatalogProduct/Index', [
            'products' => $products,
            'filters' => $request->only(['product_type', 'family_id', 'search']),
            'product_families' => ProductFamily::all(),
        ]);
    }

    public function create()
    {
        $lastProduct = Product::latest('id')->first();
        $consecutive = $lastProduct ? $lastProduct->id + 1 : 1;

        $brands = Brand::get();
        $product_families = ProductFamily::get();
        $production_processes = ProductionCost::where('is_active', true)->get(['id', 'name', 'cost', 'estimated_time_seconds']);
        
        // Componentes son solo los que tienen el flag activado
        $components = Product::where('is_used_as_component', true)->whereNull('archived_at')->get(['id', 'name']);
        
        // Obtenemos los padres para poder asignarlos como "variantes" 
        $parents = Product::baseProducts()
            ->whereNull('archived_at') // Corrección: Excluir padres obsoletos
            ->with('media')
            ->get([
                'id', 'name', 'code', 'product_family_id', 'brand_id', 'material', 
                'measure_unit', 'currency', 'base_price', 'cost', 'is_sellable', 
                'is_purchasable', 'is_used_as_component'
            ]);

        return inertia('CatalogProduct/Create', [
            'brands' => $brands,
            'product_families' => $product_families,
            'production_processes' => $production_processes,
            'components' => $components,
            'parents' => $parents,
            'consecutive' => $consecutive,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:products,code',
            'product_type_key' => 'required|string|in:P,I', // P: Producto, I: Insumo
            
            // Flags booleanos
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_used_as_component' => 'boolean',
            'parent_id' => 'nullable|exists:products,id',

            'currency' => [
                'nullable',
                'required_if:is_sellable,true',
                'string',
            ],
            'caracteristics' => 'nullable|string',
            'cost' => 'required|numeric|max:99999',
            'base_price' => [
                'nullable',
                'required_if:is_sellable,true',
                'numeric',
                'min:0',
            ],
            'brand_id' => 'nullable|required_if:product_type_key,P|exists:brands,id',
            'brand_name' => 'nullable|required_if:product_type_key,I|string|max:255',
            'product_family_id' => 'nullable|required_if:product_type_key,P|exists:product_families,id',
            'material' => 'nullable|required_if:product_type_key,P|string',
            'measure_unit' => 'nullable|string|max:100',
            'min_quantity' => 'nullable|integer|min:0',
            'max_quantity' => 'nullable|integer|min:0|gte:min_quantity',
            'is_circular' => 'nullable|boolean',
            'width' => 'nullable|numeric|min:0',
            'large' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'diameter' => 'nullable|numeric|min:0',
            'media' => 'nullable|array|max:3',
            'media.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
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

        if ($validatedData['product_type_key'] === 'I') {
            $brand = \App\Models\Brand::firstOrCreate(['name' => $validatedData['brand_name']]);
            $validatedData['brand_id'] = $brand->id;
            unset($validatedData['brand_name']);
            $validatedData['product_family_id'] = null;
            $validatedData['material'] = null;
        }

        $productTypes = ['P' => 'Producto', 'I' => 'Insumo'];
        $materials = [
            'M'   => 'METAL', 'PLS' => 'PLASTICO', 'PL'  => 'PIEL DE LUJO',
            'O'   => 'ORIGINAL', 'L'   => 'LUJO', 'P'   => 'PIEL', 'ZK'  => 'ZAMAK',
            'SCH' => 'SOLIDCHROME', 'MM'  => 'MICROMETAL', 'FCH' => 'FLEXCHROME', 'AL'  => 'ALUMINIO',
            'ES'  => 'ESTIRENO', 'ABS' => 'ABS', 'PVC' => 'PVC', 'T'   => 'TELA', 'CAU' => 'CAUCHO',
            'VPL' => 'VINILPIEL', 'FC' => 'FIBRA DE CARBONO', 'OV' => 'OVERLAY', 'AC' => 'ACERO', 'FDC' => 'FIBRA DSE CARBONO',
            'RS' => 'RESINA', 'ENC' => 'ENCAPSULADO', 'CDT' => 'CORTE DIAMANTE' 
        ];

        $validatedData['product_type'] = $productTypes[$validatedData['product_type_key']];
        if (isset($validatedData['material'])) {
            $validatedData['material'] = $materials[$validatedData['material']];
        }

        // ====== LÓGICA DE CÁLCULO DE COSTO ======
        $totalCost = $validatedData['cost'] ?? 0;

        if ($request->filled('components')) {
            $totalComponentsCost = 0;
            foreach ($request->input('components') as $component) {
                $totalComponentsCost += $component['cost'] * $component['quantity'];
            }
            $totalCost += $totalComponentsCost;
        }

        if ($request->filled('production_processes')) {
            $totalProcessesCost = array_sum(array_column($request->input('production_processes'), 'cost'));
            $totalCost += $totalProcessesCost;
        }
        $validatedData['cost'] = $totalCost;

        DB::beginTransaction();
        try {
            $product = Product::create(
                $validatedData 
                + [
                    'base_price_updated_at' => now(),
                ]
            );

            $product->storages()->create([
                'quantity' => $request->current_stock ?? 0,
                'location' => $request->location 
            ]);

            if ($validatedData['product_type_key'] === 'P') {
                if ($request->filled('components')) {
                    $componentsToSync = [];
                    foreach ($request->input('components') as $component) {
                        $componentsToSync[$component['product_id']] = [
                            'quantity' => $component['quantity'],
                            'cost' => $component['cost']
                        ];
                    }
                    $product->components()->sync($componentsToSync);
                }

                if ($request->filled('production_processes')) {
                    $processesToSync = [];
                    foreach ($request->input('production_processes') as $index => $process) {
                        $processesToSync[$process['process_id']] = [
                            'order' => $index + 1,
                            'cost' => $process['cost'] 
                        ];
                    }
                    $product->productionCosts()->sync($processesToSync);
                }
            }

            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $product->addMedia($file)->toMediaCollection('images');
                }
            }

            if ($request->filled('quote_product_id')) {
                $quoteProduct = \App\Models\QuoteProduct::find($request->quote_product_id);

                if ($quoteProduct) {
                    if ($request->boolean('copy_quote_image') && $quoteProduct->hasMedia('custom_product_images')) {
                        $mediaItem = $quoteProduct->getFirstMedia('custom_product_images');
                        $mediaItem->copy($product, 'images');
                    }

                    $quoteProduct->update([
                        'product_id' => $product->id,
                        'custom_name' => null,
                        'custom_cost' => null,
                        'custom_measure_unit' => null,
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', 'Ocurrió un error inesperado al guardar el producto. Por favor, inténtelo de nuevo.');
        }

        return to_route('catalog-products.index')->with('success', 'Producto creado con éxito.');
    }

    public function show(Product $catalog_product)
    {
        // Si el producto es una variante (tiene un parent_id),
        // redirigir a la vista del padre pasando el id de la variante en la URL.
        if ($catalog_product->parent_id) {
            return redirect()->route('catalog-products.show', [
                'catalog_product' => $catalog_product->parent_id,
                'variant_id' => $catalog_product->id
            ]);
        }

        // Se agregaron 'variants.media', 'components.storages' y storages/movimientos de las variantes
        $catalog_product->load([
            'media',
            'brand',
            'productFamily',
            'storages.stockMovements' => function ($query) {
                $query->latest()->limit(100); 
            },
            'components.media',
            'components.storages', 
            'productionCosts',
            'priceHistory.branch',
            'variants.media', 
            'variants.storages.stockMovements' => function ($query) {
                $query->latest()->limit(100); 
            },
        ]);

        return Inertia::render('CatalogProduct/Show', [
            'product' => $catalog_product,
        ]);
    }

    public function edit(Product $catalog_product)
    {
        $catalog_product->load('brand', 'productFamily', 'storages', 'components', 'productionCosts', 'media');
        
        $parents = Product::baseProducts()
            ->whereNull('archived_at') // Corrección: Excluir padres obsoletos
            ->where('id', '!=', $catalog_product->id) // Evita que se asigne a sí mismo
            ->with('media')
            ->get(['id', 'name', 'code']);

        return inertia('CatalogProduct/Edit', [
            'catalog_product' => $catalog_product,
            'brands' => Brand::all(),
            'product_families' => ProductFamily::all(),
            'production_processes' => ProductionCost::all(),
            'parents' => $parents,
            'components' => Product::where('is_used_as_component', true)->whereNull('archived_at')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $catalog_product)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', Rule::unique('products')->ignore($catalog_product->id)],
            'product_type_key' => 'required|string|in:P,I',

            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_used_as_component' => 'boolean',
            'parent_id' => 'nullable|exists:products,id',

            'caracteristics' => 'nullable|string',
            'cost' => 'required|numeric|max:99999',
            'currency' => [
                'nullable',
                'required_if:is_sellable,true',
                'string',
            ],
            'base_price' => [
                'nullable',
                'required_if:is_sellable,true',
                'numeric',
                'min:0',
            ],
            'brand_id' => 'nullable|required_if:product_type_key,P|exists:brands,id',
            'brand_name' => 'nullable|required_if:product_type_key,I|string|max:255',
            'product_family_id' => 'nullable|required_if:product_type_key,P|exists:product_families,id',
            'material' => 'nullable|required_if:product_type_key,P|string',
            'measure_unit' => 'nullable|string|max:100',
            'min_quantity' => 'nullable|numeric|min:0',
            'max_quantity' => 'nullable|numeric|min:0|gte:min_quantity',
            'is_circular' => 'nullable|boolean',
            'width' => 'nullable|numeric|min:0',
            'large' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'diameter' => 'nullable|numeric|min:0',
            'media' => 'nullable|array|max:3',
            'media.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'components' => [
                'nullable',
                'array',
                Rule::when(fn ($input) => $input->hasComponents === true, ['min:2']),
            ],
            'components.*.product_id' => 'required_with:components|exists:products,id',
            'components.*.quantity' => 'required_with:components|numeric|min:1',
            'production_processes' => 'nullable|array',
            'production_processes.*.process_id' => 'required_with:production_processes|exists:production_costs,id',
            'location' => 'nullable|string|max:255',
        ]);

        if ($validatedData['product_type_key'] === 'I') {
            $brand = \App\Models\Brand::firstOrCreate(['name' => $validatedData['brand_name']]);
            $validatedData['brand_id'] = $brand->id;
            unset($validatedData['brand_name']);
            $validatedData['product_family_id'] = null;
            $validatedData['material'] = null;
        }

        $productTypes = ['P' => 'Producto', 'I' => 'Insumo'];
        $materials = [
            'M'   => 'METAL', 'PLS' => 'PLASTICO', 'PL'  => 'PIEL DE LUJO',
            'O'   => 'ORIGINAL', 'L'   => 'LUJO', 'P'   => 'PIEL', 'ZK'  => 'ZAMAK',
            'SCH' => 'SOLIDCHROME', 'MM'  => 'MICROMETAL', 'FCH' => 'FLEXCHROME', 'AL'  => 'ALUMINIO',
            'ES'  => 'ESTIRENO', 'ABS' => 'ABS', 'PVC' => 'PVC', 'T'   => 'TELA', 'CAU' => 'CAUCHO',
            'VPL' => 'VINILPIEL', 'FC' => 'FIBRA DE CARBONO', 'OV' => 'OVERLAY', 'AC' => 'ACERO', 'FDC' => 'FIBRA DSE CARBONO',
            'RS' => 'RESINA', 'ENC' => 'ENCAPSULADO', 'CDT' => 'CORTE DIAMANTE'
        ];

        $validatedData['product_type'] = $productTypes[$validatedData['product_type_key']];
        if (isset($validatedData['material'])) {
            $validatedData['material'] = $materials[$validatedData['material']];
        }

        $totalCost = $validatedData['cost'] ?? 0;

        if ($request->filled('components')) {
            $totalComponentsCost = 0;
            foreach ($request->input('components') as $component) {
                $totalComponentsCost += $component['cost'] * $component['quantity'];
            }
            $totalCost += $totalComponentsCost;
        }

        if ($request->filled('production_processes')) {
            $totalProcessesCost = array_sum(array_column($request->input('production_processes'), 'cost'));
            $totalCost += $totalProcessesCost;
        }

        $validatedData['cost'] = $totalCost;

        if ($catalog_product->base_price != $request->base_price) {
            $validatedData['base_price_updated_at'] = now();
        }

        DB::beginTransaction();
        try {
            $catalog_product->update($validatedData);

            $storage = $catalog_product->storages()->first();
            if ($storage) {
                $storage->update([
                    'location' => $request->location,
                    'quantity' => $request->current_stock
                ]);
            }
            
            if ($validatedData['product_type_key'] !== 'P') {
                $catalog_product->components()->sync([]);
                $catalog_product->productionCosts()->sync([]);
            } else {
                $componentsToSync = [];
                if ($request->filled('components')) {
                    foreach ($request->input('components') as $component) {
                        $componentsToSync[$component['product_id']] = [
                            'quantity' => $component['quantity'],
                            'cost' => $component['cost']
                        ];
                    }
                }
                $catalog_product->components()->sync($componentsToSync);

                $processesToSync = [];
                if ($request->filled('production_processes')) {
                    foreach ($request->input('production_processes') as $index => $process) {
                        $processesToSync[$process['process_id']] = [
                            'order' => $index + 1,
                            'cost' => $process['cost'] 
                        ];
                    }
                }
                $catalog_product->productionCosts()->sync($processesToSync);
            }

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

        return to_route('catalog-products.show', $catalog_product->id);
    }

    public function destroy(Product $catalog_product)
    {
        $catalog_product->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $product = Product::find($id);
            $product?->delete();
        }
    }

    public function searchRawMaterial(Request $request)
    {
        $query = $request->input('query');

        $rawMaterials = Product::where('is_used_as_component', true)
                            ->whereNull('archived_at') // Corrección: Excluir componentes obsoletos
                            ->where('name', 'LIKE', "%{$query}%")
                            ->select('id', 'name') 
                            ->limit(10)
                            ->get();

        return response()->json($rawMaterials);
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        $catalog_products = Product::with('media')
            ->whereNull('archived_at') // Corrección: Excluir de la búsqueda general los obsoletos
            ->latest()
            ->select(['id', 'brand_id', 'code', 'name', 'cost', 'material', 'archived_at']) 
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                ->orWhere('code', 'like', "%{$query}%")
                ->orWhere('material', 'like', "%{$query}%")
                ->orWhereHas('brand', function ($brandQuery) use ($query) {
                    $brandQuery->where('name', 'like', "%{$query}%");
                });
            })
            ->get();

        return response()->json(['items' => $catalog_products], 200);
    }

    public function getProductMedia(Product $product)
    {
        $product->load(['media', 'storages', 'components.media', 'components.storages']);

        return response()->json(compact('product'));
    }

    public function findSimilar(Request $request): JsonResponse
    {
        $baseCode = $request->input('base_code');

        $similarProducts = Product::where('code', 'LIKE', $baseCode . '-%')
            ->whereNull('archived_at') // Corrección: Excluir sugerencias obsoletas
            ->with('media')
            ->get(['id', 'name', 'code', 'caracteristics']);

        return response()->json($similarProducts);
    }

    public function fetchProducts(Request $request)
    {   
        $productType = $request->params['product_type'];
        
        $products = Product::where('product_type', $productType)
            ->whereNull('archived_at') // Corrección: Traer sólo productos activos
            ->get(['id', 'name', 'code']);

        return response()->json($products);
    }

    public function handleStockMovement(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'type' => 'required|in:Entrada,Salida',
            'notes' => 'nullable|string|max:500',
        ]);

        $storage = $product->storages()->first();
        
        if (!$storage) {
            return back()->withErrors(['message' => 'El producto no tiene un almacén asociado.']);
        }
        
        if ($validated['type'] === 'Salida' && $storage->quantity < $validated['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => 'No hay existencias suficientes para realizar la salida. Stock actual: ' . $storage->quantity,
            ]);
        }
            $quantityChange = $validated['quantity'];

            if ($validated['type'] === 'Entrada') {
                $storage->increment('quantity', $quantityChange);
            } else {
                $storage->decrement('quantity', $quantityChange);
            }

            $storage->stockMovements()->create([
                'product_id' => $product->id,
                'quantity_change' => $quantityChange,
                'type' => $validated['type'],
                'notes' => $validated['notes'],
            ]);
    }

    public function markAsObsolet(Product $product)
    {
        if ( $product->archived_at ) {
            $product->update(['archived_at' => null]);
        } else {
            $product->update(['archived_at' => now()]);
        }
    }

    public function massiveObsolet(Request $request)
    {
        $request->validate(['ids' => 'required|array']);

        $products = Product::whereIn('id', $request->ids)->get();

        foreach ($products as $product) {
            if ($product->archived_at) {
                $product->archived_at = null; 
            } else {
                $product->archived_at = now(); 
            }
            $product->save();
        }

        return redirect()->back()->with('message', 'Los productos seleccionados han sido actualizados.');
    }

    public function simpleUpdate(Request $request, Product $product)
    {
        $request->validate([
            'base_price' => 'required|numeric|min:0',
        ]);

        $product->update([
            'base_price' => $request->base_price,
            'base_price_updated_at' => now(),
        ]);
    }

    public function pricesReport()
    {
        $catalog_products = Product::where('product_type', 'Producto')
            ->where('is_sellable', true)
            ->whereNull('archived_at') // Este ya lo tenías correctamente filtrado
            ->with([
                'media',
                'priceHistory' => function ($query) {
                    $query->whereNull('valid_to')->with('branch:id,name'); 
                }
            ])
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'cost', 'base_price']);

        return inertia('CatalogProduct/PricesReport', compact('catalog_products'));
    }
    
    public function exportExcel()
    {
        return Excel::download(new CatalogProductPricesExportPriceABC, 'catalogo_precios.xlsx');
    }

    public function exportExcelABC()
    {
        return Excel::download(new CatalogProductPricesExportABC, 'catalogo_precios_ABC_clientes_nuevos.xlsx');
    }

    public function fetchProductsList()
    {
        $type = request('type');

        if ( $type === 'Todos' ) {
            $products = Product::query()
                ->whereNull('parent_id') // Solo productos "padre"
                ->whereNull('archived_at') // Corrección: Ocultar obsoletos
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        } else {
            $products = Product::query()
                ->whereNull('archived_at') // Corrección: Ocultar obsoletos
                ->select('id', 'name')
                ->where('product_type', $type)
                ->orderBy('name')
                ->get();
        }

        return response()->json($products);
    }

    public function massiveUpdate(Request $request)
    {
        $materialMap = $this->getMaterialMap();
        $productTypesMap = ['P' => 'Producto', 'I' => 'Insumo']; 
        
        $validatedData = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.product_type_key' => 'nullable|in:P,I', 
            'products.*.product_family_id' => 'nullable|exists:product_families,id',
            'products.*.material' => ['nullable', 'string', Rule::in(array_keys($materialMap))],
            'products.*.is_used_as_component' => 'present|boolean',
            'products.*.is_sellable' => 'present|boolean',
            'products.*.is_purchasable' => 'present|boolean',
            'products.*.parent_id' => 'nullable|exists:products,id',
        ]);
    
        DB::beginTransaction();
        try {
            foreach ($validatedData['products'] as $productData) {
                $product = Product::with('brand', 'productFamily')->find($productData['id']);
                if (!$product) continue;
    
                $updateData = [
                    'product_family_id' => $productData['product_family_id'],
                    'is_used_as_component' => $productData['is_used_as_component'],
                    'is_sellable' => $productData['is_sellable'],
                    'is_purchasable' => $productData['is_purchasable'],
                    'parent_id' => $productData['parent_id'] ?? null,
                    'material' => isset($productData['material']) ? $materialMap[$productData['material']] : null,
                ];

                if (isset($productData['product_type_key'])) {
                    $newType = $productTypesMap[$productData['product_type_key']];
                    $updateData['product_type'] = $newType;
                    
                    if ($productData['product_type_key'] === 'I') {
                        $updateData['product_family_id'] = null;
                        $updateData['material'] = null;
                    }
                }
                
                $product->update($updateData);
    
                $product->refresh(); 
    
                $type = $product->product_type === 'Producto' ? 'P' : 'I';
                
                if ($type === 'P') {
                    $id = $product->id;
                    $family = $product->productFamily->key ?? '';
                    $materialKey = isset($productData['material']) 
                        ? $productData['material'] 
                        : (array_search($product->material, $materialMap) ?: '');
                    
                    $brand = $product->brand ? strtoupper(substr($product->brand->name, 0, 3)) : '';
        
                    $newCode = "{$family}-{$materialKey}-{$brand}-{$id}";
                    $product->update(['code' => $newCode]);
                }
            }
    
            DB::commit();
    
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['massive_update' => 'Ocurrió un error al actualizar los productos: ' . $e->getMessage()]);
        }
    
        return redirect()->route('catalog-products.index')->with('success', 'Productos actualizados correctamente.');
    }

    public function searchParents(Request $request)
    {
        $query = $request->input('query');

        $parents = Product::query()
            ->select(['id', 'name', 'code'])
            ->whereNull('archived_at') // Corrección: Excluir padres obsoletos de las búsquedas en vivo
            ->where('product_type', '!=', 'Insumo') // Evitamos que traiga insumos
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('code', 'like', "%{$query}%");
            })
            ->limit(50)
            ->get();

        return response()->json(['items' => $parents], 200);
    }

    private function getMaterialMap()
    {
        return [
            'M'   => 'METAL', 'PLS' => 'PLASTICO', 'PL'  => 'PIEL DE LUJO', 'O'   => 'ORIGINAL',
            'L'   => 'LUJO', 'P'   => 'PIEL', 'ZK'  => 'ZAMAK', 'SCH' => 'SOLIDCHROME',
            'MM'  => 'MICROMETAL', 'FCH' => 'FLEXCHROME', 'AL'  => 'ALUMINIO', 'ES'  => 'ESTIRENO',
            'ABS' => 'ABS', 'PVC' => 'PVC', 'T'   => 'TELA', 'CAU' => 'CAUCHO', 'VPL' => 'VINILPIEL', 'FC' => 'FIBRA DE CARBONO',
        ];
    }
}