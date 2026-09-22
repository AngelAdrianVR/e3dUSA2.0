<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\NewProductProposal;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SampleTracking;
use App\Models\StockMovement;
use App\Models\Storage;
use App\Notifications\SaleAuthorizedNotification;
use App\Services\MuestraProductService;
use App\Services\ShippingRateSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Jobs\CheckLowStockAndNotifyJob; // AGREGADO: Job para revisar stock y notificar si está por debajo del minimo permitido

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->query('view');
        $user = Auth::user();

        // Por defecto se muestran TODAS las ventas (si el usuario tiene permiso).
        // El parámetro 'mias' fuerza a mostrar solo las del usuario.
        $showAll = $view !== 'mias' && $user->hasPermissionTo('Ver todas las ventas');
        $filterPending = $request->query('filter') === 'pending'; // Pendiente de seguimiento (autorizadas)
        $filterPendingAuth = $request->query('filter') === 'pending_authorization'; // Pendiente por autorizar

        $query = Sale::query();

        if (!$showAll) {
            $query->where('user_id', Auth::id());
        }

        // Filtro: Pendiente de seguimiento (autorizadas)
        if ($filterPending) {
            $query->where('status', 'Autorizada');
        }

        // Filtro: Pendiente por autorizar
        if ($filterPendingAuth) {
            $query->where('status', 'Pendiente');
        }

        // AGREGADO: 'productExchanges.returnedProduct:id,name' y 'productExchanges.newProduct:id,name'
        // para tener la info del tooltip sin cargar toda la base de datos.
        $sales = $query->with([
                        'user:id,name', 
                        'branch:id,name', 
                        'saleProducts.product:id,name,cost', 
                        'invoice:id,folio,sale_id',
                        'quote:id,root_quote_id', // Para obtener el root_quote_id de la cotización padre
                        'productExchanges.returnedProduct:id,name',
                        'productExchanges.newProduct:id,name',
                        'shipments:id,sale_id,status,shipping_company,tracking_guide,promise_date'
                    ])
                    ->select('id', 'currency', 'branch_id', 'quote_id', 'user_id', 'invoice_id', 'type', 'status', 'total_amount', 'created_at', 'is_high_priority', 'authorized_user_name', 'authorized_at', 'pre_invoice_folio', 'stamped_invoice_folio', 'billing_status', 'promise_date')
                    ->latest() 
                    ->paginate(15) 
                    ->withQueryString();

        // Para cada venta con cotización, obtenemos el root_quote_id (para mostrar el folio padre)
        // y el ID de la versión activa más reciente (para el enlace).
        $quoteIds = $sales->pluck('quote_id')->unique()->filter();
        if ($quoteIds->isNotEmpty()) {
            $quotesData = Quote::whereIn('id', $quoteIds)
                ->select('id', 'root_quote_id')
                ->get()
                ->keyBy('id');

            // Obtener los root_quote_id únicos y buscar su versión activa
            $rootIds = $quotesData->pluck('root_quote_id')->unique()->filter();
            $activeVersions = collect();
            if ($rootIds->isNotEmpty()) {
                $activeVersions = Quote::whereIn('root_quote_id', $rootIds)
                    ->where('is_active', true)
                    ->select('id', 'root_quote_id')
                    ->orderBy('version', 'desc')
                    ->get()
                    ->keyBy('root_quote_id');
            }

            $sales->each(function ($sale) use ($quotesData, $activeVersions) {
                if ($sale->quote_id && isset($quotesData[$sale->quote_id])) {
                    $rootId = $quotesData[$sale->quote_id]->root_quote_id;
                    $sale->quote_root_id = $rootId;                       // Para mostrar COT-{root}
                    $sale->quote_active_id = $activeVersions->get($rootId)?->id ?? $sale->quote_id; // Para el enlace
                }
            });
        }

        // Seguimiento de muestra vinculado (para órdenes de tipo muestra/regalo: MUE-xxxx)
        $sampleTrackingBySale = SampleTracking::whereIn('sale_id', $sales->pluck('id'))
            ->select('id', 'sale_id')
            ->get()
            ->keyBy('sale_id');

        $sales->each(function ($sale) use ($sampleTrackingBySale) {
            $sale->sample_tracking_id = $sampleTrackingBySale->get($sale->id)?->id;
        });
        
        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['view', 'filter']),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'quote_id' => 'nullable|integer|exists:quotes,id',
            'sample_tracking_id' => 'nullable|integer|exists:sample_trackings,id',
        ]);

        $quoteToConvertId = intval($request->input('quote_id'));

        // --- ORDEN DE MUESTRA/REGALO: datos del seguimiento de muestra (para prellenar el form) ---
        $sampleTrackingData = $this->getSampleTrackingDataForSale($request->input('sample_tracking_id'));

        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Excluir familias de cotizaciones donde ALGUNA versión ya tiene OV vinculada
        $familiesWithSale = Quote::whereNotNull('sale_id')->pluck('root_quote_id')->unique()->filter();

        $quotes = Quote::where('authorized_at', '!=', null)
                    ->latest()
                    ->where('is_active', true)
                    ->where('status', 'Aceptada')
                    ->when($familiesWithSale->isNotEmpty(), function ($q) use ($familiesWithSale) {
                        $q->whereNotIn('root_quote_id', $familiesWithSale);
                    })
                    ->select('id', 'branch_id', 'sale_id')
                    ->with('branch:id,name')
                    ->take(100)
                    ->get();
        
        // MODIFICACIÓN: Cargar productos padre (parent_id es null) junto con sus variantes y multimedia
        $catalog_products = Product::whereNull('parent_id')
                    ->whereNull('archived_at')
                    ->with(['variants' => function($q) {
                        $q->whereNull('archived_at')->select('id', 'parent_id', 'name', 'code');
                    }, 'variants.media', 'media'])
                    ->select('id', 'name', 'code')
                    ->get();

        // Productos de la categoría "Muestras y regalos": se pueden agregar a las órdenes
        // de muestra/regalo sin necesidad de estar vinculados a ningún cliente.
        $muestra_products = Product::where('product_type', 'Muestra')
                    ->whereNull('archived_at')
                    ->with('media')
                    ->select('id', 'name', 'code')
                    ->get();

        return Inertia::render('Sale/Create', [
            'branches' => $branches,
            'quotes' => $quotes,
            'catalog_products' => $catalog_products,
            'quoteToConvertId' => $quoteToConvertId,
            'sampleTrackingData' => $sampleTrackingData,
            'muestra_products' => $muestra_products,
        ]);
    }

    /**
     * Prepara la información de un seguimiento de muestra para prellenar la Orden de
     * Venta de Muestra/Regalo (cliente, contacto, productos y notas).
     *
     * Los artículos 'nuevos' (NewProductProposal) se envían con su id de propuesta; si ya
     * fueron registrados en el catálogo (proposal->product_id) se envían como producto normal.
     */
    private function getSampleTrackingDataForSale($sampleTrackingId): ?array
    {
        if (!$sampleTrackingId) {
            return null;
        }

        $sampleTracking = SampleTracking::with(['items.itemable.media'])->whereKey($sampleTrackingId)->first();

        if (!$sampleTracking) {
            return null;
        }

        return [
            'id' => $sampleTracking->id,
            'name' => $sampleTracking->name,
            'branch_id' => $sampleTracking->branch_id,
            'contact_id' => $sampleTracking->contact_id,
            'will_be_returned' => (bool) $sampleTracking->will_be_returned,
            'sale_id' => $sampleTracking->sale_id,
            'items' => $sampleTracking->items->map(function ($item) {
                $isProposal = $item->itemable_type === NewProductProposal::class;

                return [
                    'type' => $isProposal ? 'new' : 'catalog',
                    'product_id' => $isProposal ? $item->itemable?->product_id : $item->itemable_id,
                    'new_product_proposal_id' => $isProposal ? $item->itemable_id : null,
                    'name' => $item->itemable?->name ?? 'Producto',
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'image_url' => $item->itemable && method_exists($item->itemable, 'getFirstMediaUrl')
                        ? $item->itemable->getFirstMediaUrl('images')
                        : null,
                ];
            })->values()->all(),
        ];
    }

    public function store(Request $request)
    {
        // --- 1. DETERMINAR TIPO Y REGLAS BASE ---
        // 'muestra' = Orden de Venta para muestras que no serán devueltas o productos
        // regalados. Comparte las reglas de 'venta' (cliente, contacto, logística y
        // envíos), pero el precio es opcional y NO mueve inventario ni genera producción:
        // se crea únicamente para poder facturarse.
        $type = $request->input('type');
        $isVentaType = $type === 'venta';
        $isMuestraType = $type === 'muestra';
        $isSaleType = $isVentaType || $isMuestraType;

        $rules = [
            'type' => ['required', Rule::in(['venta', 'stock', 'muestra'])],
            'oce_name' => 'nullable|string|max:255|unique:sales,oce_name',
            'notes' => 'nullable|string',
            'currency' => 'nullable|string',
            'is_high_priority' => 'required|boolean',
            'has_low_price' => 'boolean', // NUEVO CAMPO
            'low_price_reason' => 'nullable|string', // NUEVO CAMPO AGREGADO A LA RAIZ
            'tooling_cost' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
            'products.*.has_low_price' => 'boolean', // AGREGADO
            'products.*.low_price_reason' => 'nullable|string', // AGREGADO
            'oce_media' => 'nullable|array|max:3',
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:10000',
        ];

        // --- 2. AÑADIR REGLAS CONDICIONALES PARA 'VENTA' ---
        if ($isSaleType) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
            $rules['contact_id'] = ['required', 'exists:contacts,id'];
            $rules['quote_id'] = ['nullable', 'exists:quotes,id'];
            $rules['order_via'] = ['nullable', 'string', 'max:255'];
            $rules['freight_option'] = ['required', 'string', 'max:255'];
            $rules['freight_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['shipping_option'] = ['required', 'string'];
            
            // En 'venta' el precio es obligatorio; en 'muestra' es opcional (la empresa puede absorber el costo).
            $rules['products.*.price'] = $isVentaType ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'];

            if ($isMuestraType) {
                $rules['sample_tracking_id'] = ['nullable', 'exists:sample_trackings,id'];
            }

            $rules['shipments'] = ['required', 'array', 'min:1'];
            $rules['shipments.*.promise_date'] = ['nullable', 'date'];
            $rules['shipments.*.shipping_company'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.tracking_guide'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.acknowledgement_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:10000'];
            $rules['shipments.*.products'] = ['required', 'array'];
            $rules['shipments.*.products.*.product_id'] = ['required', 'exists:products,id'];
            $rules['shipments.*.products.*.quantity'] = ['required', 'integer', 'min:0'];
        } else {
            // Regla para 'stock': el precio no es requerido, pero si se envía debe ser numérico.
            $rules['products.*.price'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

        // --- OBTENER LA FECHA PROMESA DEL PRIMER ENVÍO ---
        $firstPromiseDate = ($isSaleType && !empty($validated['shipments'])) ? ($validated['shipments'][0]['promise_date'] ?? null) : null;

        // --- VALIDAR QUE EL SEGUIMIENTO DE MUESTRA NO TENGA YA UNA ORDEN VINCULADA ---
        // Cada seguimiento de muestra solo puede generar UNA Orden de Venta. Además, la OV de
        // muestra/regalo hereda el estatus del seguimiento del que proviene.
        $sampleTrackingForSale = null;
        if ($isMuestraType && !empty($validated['sample_tracking_id'])) {
            $sampleTrackingForSale = SampleTracking::whereKey($validated['sample_tracking_id'])->first();

            if ($sampleTrackingForSale?->sale_id) {
                throw ValidationException::withMessages([
                    'sample_tracking_id' => 'Este seguimiento de muestra ya tiene una Orden de Venta vinculada; no se puede crear otra.',
                ]);
            }
        }

        DB::beginTransaction();

        try {
            // --- 3. CREAR LA ORDEN (VENTA O STOCK) ---
            $sale = Sale::create([
                'type' => $validated['type'],
                'user_id' => auth()->id(),
                'status' => $isMuestraType ? ($sampleTrackingForSale?->status ?? 'Pendiente') : 'Pendiente', // En muestra/regalo hereda el estatus del seguimiento
                // En 'muestra' no aplican precios bajos ni costo de herramental.
                'has_low_price' => $isMuestraType ? false : ($validated['has_low_price'] ?? false),
                'oce_name' => $validated['oce_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'is_high_priority' => $validated['is_high_priority'],
                'tooling_cost' => $isMuestraType ? null : ($validated['tooling_cost'] ?? null),
                'promise_date' => $firstPromiseDate, // Se asigna la fecha del primer envío
                
                // Campos que son nulos para 'stock'
                'branch_id' => $validated['branch_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'quote_id' => $isMuestraType ? null : ($validated['quote_id'] ?? null),
                'order_via' => $validated['order_via'] ?? null,
                'freight_option' => $validated['freight_option'] ?? null,
                'freight_cost' => $validated['freight_cost'] ?? 0,
                'shipping_option' => $validated['shipping_option'] ?? null,
                
                'total_amount' => $isSaleType ? array_reduce($validated['products'], function ($carry, $product) {
                    return $carry + ($product['quantity'] * ($product['price'] ?? 0));
                }, 0) : 0,
            ]);
            
            if ($isVentaType && !empty($validated['quote_id'])) {
                Quote::find($validated['quote_id'])->update([
                    'sale_id' => $sale->id,
                    'status' => 'Aceptada'
                ]);
            }

            // --- VINCULAR EL SEGUIMIENTO DE MUESTRA CON LA ORDEN (solo muestra/regalo) ---
            if ($isMuestraType && !empty($validated['sample_tracking_id'])) {
                SampleTracking::where('id', $validated['sample_tracking_id'])->update(['sale_id' => $sale->id]);
            }

            // --- 4. GUARDAR PRODUCTOS DE LA ORDEN ---
            $saleProductsMap = [];
            foreach ($validated['products'] as $productData) {
                $saleProduct = $sale->saleProducts()->create([
                    'product_id' => $productData['id'],
                    'quantity' => $productData['quantity'],
                    'price' => $productData['price'] ?? 0,
                    'notes' => $productData['notes'] ?? null,
                    'customization_details' => $productData['customization_details'] ?? null,
                    'has_low_price' => $isMuestraType ? false : ($productData['has_low_price'] ?? false), // AGREGADO
                    'low_price_reason' => $isMuestraType ? null : ($productData['low_price_reason'] ?? null), // AGREGADO
                    'quantity_produced' => 0,
                    'quantity_shipped' => 0,
                    // En 'muestra' no hay nada que producir: la orden es solo para facturar.
                    'quantity_to_produce' => $isMuestraType ? 0 : $productData['quantity'],
                ]);
                $saleProductsMap[$productData['id']] = $saleProduct->id;
            }

            // --- 5. NUEVO: CREAR ENVÍOS Y SUS PRODUCTOS (SOLO PARA VENTAS) ---
            if ($isSaleType && !empty($validated['shipments'])) {
                foreach ($validated['shipments'] as $shipmentData) {
                    // Crear el envío asociado a la venta
                    $shipment = $sale->shipments()->create([
                        'status' => 'Pendiente',
                        'promise_date' => $shipmentData['promise_date'] ?? null,
                        'shipping_company' => $shipmentData['shipping_company'] ?? null,
                        'tracking_guide' => $shipmentData['tracking_guide'] ?? null,
                    ]);

                    // Asociar productos al envío
                    if (!empty($shipmentData['products'])) {
                        foreach ($shipmentData['products'] as $productShipmentData) {
                            // Usar el mapa para encontrar el ID del producto de la venta correspondiente
                            $saleProductId = $saleProductsMap[$productShipmentData['product_id']] ?? null;

                            if ($saleProductId && $productShipmentData['quantity'] > 0) {
                                $shipment->shipmentProducts()->create([
                                    'sale_product_id' => $saleProductId,
                                    'quantity' => $productShipmentData['quantity'],
                                ]);
                            }
                        }
                    }
                }
            }
            // --- 6. LÓGICA DE INVENTARIO Y PRODUCCIÓN (PARA VENTAS Y STOCK) ---
            // NOTA: las órdenes de muestra/regalo NO mueven inventario ni generan
            // producción; se crean únicamente para poder facturar la muestra/regalo.
            foreach ($validated['products'] as $productData) {
                if ($isMuestraType) {
                    continue;
                }

                // CORRECCIÓN: Cargar parent.components.storages para que las variantes encuentren el stock de sus componentes heredados
                $product = Product::with(['storages', 'components.storages', 'parent.components.storages'])->find($productData['id']);
                $quantityInTransaction = $productData['quantity']; // Renombrado para mayor claridad

                $quantityToProduce = 0;

                // --- Lógica específica para ventas ---
                if ($isSaleType) {
                    $saleProduct = SaleProduct::where('sale_id', $sale->id)->where('product_id', $product->id)->first();
                    $stockFinishedProduct = $product->storages->first()?->quantity ?? 0;

                    // 6.1 Calcular cuánto se toma de stock y cuánto se produce
                    $takenFromStock = min($quantityInTransaction, $stockFinishedProduct);
                    $quantityToProduce = $quantityInTransaction - $takenFromStock;
                    
                    $saleProduct->update(['quantity_to_produce' => $quantityToProduce]);

                    // 6.2 Descontar stock del producto terminado y registrar movimiento
                    if ($takenFromStock > 0) {
                        $storage = $product->storages->first();
                        $storage->decrement('quantity', $takenFromStock);
                        
                        StockMovement::create([
                            'product_id' => $product->id,
                            'storage_id' => $storage->id,
                            'quantity_change' => $takenFromStock,
                            'type' => 'Salida',
                            'notes' => "Descuento por Orden de venta #{$sale->id}"
                        ]);
                    }
                } else {
                    // Para movimientos que no son ventas (ej. ajustes de stock para producción),
                    // se asume que toda la cantidad es para producir.
                    $quantityToProduce = $quantityInTransaction;
                }

                // 6.3 Descontar stock de los componentes del producto vendido.
                // CORRECCIÓN: Antes solo se descontaban componentes si había algo "por producir"
                // (quantityToProduce > 0); si el producto terminado tenía stock, los componentes
                // NUNCA se descontaban ni se registraba su movimiento. Ahora, si el producto tiene
                // componentes, se descuenta la cantidad COMPLETA de la orden y se registra el
                // movimiento de stock correspondiente.
                if ($product->actual_components->isNotEmpty()) {
                    foreach ($product->actual_components as $component) {
                        $requiredQuantity = $component->pivot->quantity * $quantityInTransaction;
                        $componentStorage = $component->storages->first();

                        if ($componentStorage && $componentStorage->quantity > 0) {
                            $currentStock = $componentStorage->quantity;
                            $discountQuantity = min($requiredQuantity, $currentStock);

                            // Se utiliza decrement() para hacer la operación más segura (evitar race conditions)
                            $componentStorage->decrement('quantity', $discountQuantity);

                            if ($discountQuantity > 0) {
                                // La nota se ajusta dinámicamente si es una venta o un movimiento de stock.
                                $notes = $isSaleType 
                                    ? "Descuento de componentes para {$quantityInTransaction} de {$product->name} (Orden #{$sale->id})"
                                    : "Descuento de componentes para {$quantityInTransaction} de {$product->name} para stock.";

                                StockMovement::create([
                                    'product_id' => $component->id,
                                    'storage_id' => $componentStorage->id,
                                    'quantity_change' => $discountQuantity,
                                    'type' => 'Salida',
                                    'notes' => $notes
                                ]);
                            }
                        }
                    }
                }
            }

            // --- 6b. DESCUENTO DE STOCK DE PRODUCTOS DE "MUESTRAS Y REGALOS" ---
            // Las piezas de muestra/regalo se descuentan al crear la orden para que queden
            // comprometidas y no se puedan tomar en otra orden (no generan producción).
            if ($isMuestraType) {
                $this->applyMuestraStockForSale($sale);
            }
            
            // --- 7. MANEJAR ARCHIVOS ADJUNTOS ---
            if ($request->hasFile('oce_media')) {
                $sale->addMultipleMediaFromRequest(['oce_media'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('oce_media');
                });
            }

            DB::commit();

            // --------------------------------------------------------------------------
            // ---> NUEVO: DESPACHAR EL JOB PARA VERIFICAR STOCK Y NOTIFICAR
            // Se ejecuta después del commit para asegurar que los descuentos 
            // de inventario ya están aplicados en la base de datos.
            // NOTA: en órdenes de muestra/regalo no hay movimientos de inventario, no aplica.
            // --------------------------------------------------------------------------
            if (!$isMuestraType) {
                CheckLowStockAndNotifyJob::dispatch($sale);
            }
            // <--- FIN NUEVO

            Log::info("Órden #{$sale->id} (tipo: {$sale->type}) creada por el usuario " . auth()->id());

            return redirect()->route('sales.show', $sale->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear la órden: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->withInput()->withErrors(['generic_error' => 'Ocurrió un error inesperado. Por favor, contacta a soporte.']);
        }
    }

    public function show(Sale $sale)
    {
        $sale->load([
            'branch:id,name,rfc,address,post_code,status',
            'media',
            'user:id,name',
            'productions.tasks', 
            'saleProducts.product.media',
            'saleProducts.product.parent.media',
            
            'saleProducts.product.priceHistory' => function ($q) {
                $q->with('user')->orderBy('created_at', 'desc'); 
            },
            
            // --- AQUÍ ESTÁN LOS CAMBIOS PARA EL STOCK COMPUESTO ---
            'saleProducts.product.storages', // Almacenes del producto simple
            'saleProducts.product.components.storages', // Almacenes de los componentes (si es padre compuesto)
            'saleProducts.product.parent.components.storages', // Almacenes de los componentes (si es hijo/variante)
            // -----------------------------------------------------

            'shipments',
            'contact:id,name,prefix',
            'contact.details',
            'productExchanges.returnedProduct',
            'productExchanges.newProduct',
            'productExchanges.user',
            'productExchanges.media',
        ]);

        $storages = \App\Models\Storage::select('id', 'location')->get();
        
        $products = [];
        
        if ($sale->branch_id) {
            $branch = \App\Models\Branch::find($sale->branch_id);
            
            $productSourceBranch = $branch->parent_branch_id 
                ? \App\Models\Branch::find($branch->parent_branch_id) 
                : $branch;

            $products = $productSourceBranch->products()
                ->where('is_sellable', true)
                ->select('products.id', 'products.name', 'products.code')
                ->get();
        }

        return Inertia::render('Sale/Show', [
            'sale' => $sale,
            'storages' => $storages,
            'products' => $products,
            'suggestedShippingRates' => (new ShippingRateSuggestionService())->forSale($sale),
            'linkedSampleTracking' => SampleTracking::where('sale_id', $sale->id)->select('id', 'name', 'status', 'will_be_returned')->first(),
        ]);
    }

    public function edit(Sale $sale)
    {
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Excluir familias de cotizaciones donde ALGUNA versión ya tiene OV vinculada
        $familiesWithSale = Quote::whereNotNull('sale_id')->pluck('root_quote_id')->unique()->filter();

        $quotes = Quote::where('authorized_at', '!=', null)
                    ->latest()
                    ->where('is_active', true)
                    ->where('status', 'Aceptada')
                    ->when($familiesWithSale->isNotEmpty(), function ($q) use ($familiesWithSale) {
                        $q->whereNotIn('root_quote_id', $familiesWithSale);
                    })
                    ->select('id', 'branch_id', 'sale_id')
                    ->with('branch:id,name')
                    ->take(100)
                    ->get();
        
        // MODIFICACIÓN: Igualmente en edición, cargar productos con estructura parent-variant
        $catalog_products = Product::whereNull('parent_id')
                    ->whereNull('archived_at')
                    ->with(['variants' => function($q) {
                        $q->whereNull('archived_at')->select('id', 'parent_id', 'name', 'code');
                    }, 'variants.media', 'media'])
                    ->select('id', 'name', 'code')
                    ->get();

        // Productos de la categoría "Muestras y regalos": se pueden agregar a las órdenes
        // de muestra/regalo sin necesidad de estar vinculados a ningún cliente.
        $muestra_products = Product::where('product_type', 'Muestra')
                    ->whereNull('archived_at')
                    ->with('media')
                    ->select('id', 'name', 'code')
                    ->get();

        return Inertia::render('Sale/Edit', [
            'branches' => $branches,
            'quotes' => $quotes,
            'catalog_products' => $catalog_products,
            'sale' => $sale->load(['branch.contacts', 'saleProducts.product.media', 'shipments.shipmentProducts.saleProduct.product', 'media']),
            'linkedSampleTracking' => SampleTracking::where('sale_id', $sale->id)->select('id', 'name', 'status', 'will_be_returned')->first(),
            'muestra_products' => $muestra_products,
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        // --- 1. DETERMINAR TIPO Y REGLAS BASE ---
        // 'muestra' comparte las reglas de 'venta' (cliente, contacto, logística y envíos),
        // pero el precio es opcional y NO mueve inventario ni genera producción.
        $isVentaType = $sale->type === 'venta';
        $isMuestraType = $sale->type === 'muestra';
        $isSaleType = $isVentaType || $isMuestraType;

        $rules = [
            'oce_name' => 'nullable|string|max:255|unique:sales,oce_name,' . $sale->id,
            'notes' => 'nullable|string',
            'currency' => 'nullable|string',
            'is_high_priority' => 'required|boolean',
            'has_low_price' => 'boolean',
            'low_price_reason' => 'nullable|string', // NUEVO CAMPO AGREGADO A LA RAIZ
            'tooling_cost' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
            'products.*.has_low_price' => 'boolean', // AGREGADO
            'products.*.low_price_reason' => 'nullable|string', // AGREGADO
            'oce_media' => 'nullable|array|max:3',
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:10000',
        ];

        // --- 2. AÑADIR REGLAS CONDICIONALES PARA 'VENTA' ---
        if ($isSaleType) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
            $rules['contact_id'] = ['required', 'exists:contacts,id'];
            $rules['quote_id'] = ['nullable', 'exists:quotes,id'];
            $rules['order_via'] = ['nullable', 'string', 'max:255'];
            $rules['freight_option'] = ['required', 'string', 'max:255'];
            $rules['freight_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['shipping_option'] = ['required', 'string'];
            
            // En 'venta' el precio es obligatorio; en 'muestra' es opcional (la empresa puede absorber el costo).
            $rules['products.*.price'] = $isVentaType ? ['required', 'numeric', 'min:0'] : ['nullable', 'numeric', 'min:0'];

            // Reglas para envíos
            $rules['shipments'] = ['required', 'array', 'min:1'];
            $rules['shipments.*.id'] = ['nullable', 'exists:shipments,id']; // Para identificar envíos existentes
            $rules['shipments.*.promise_date'] = ['nullable', 'date'];
            $rules['shipments.*.shipping_company'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.tracking_guide'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.acknowledgement_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:10000'];
            $rules['shipments.*.products'] = ['required', 'array'];
            $rules['shipments.*.products.*.product_id'] = ['required', 'exists:products,id'];
            $rules['shipments.*.products.*.quantity'] = ['required', 'integer', 'min:0'];
        } else {
            $rules['products.*.price'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

        // --- OBTENER LA FECHA PROMESA DEL PRIMER ENVÍO ---
        $firstPromiseDate = ($isSaleType && !empty($validated['shipments'])) ? ($validated['shipments'][0]['promise_date'] ?? null) : null;

        DB::beginTransaction();

        try {

            // --- 3. REVERTIR MOVIMIENTOS DE STOCK ANTERIORES (SOLO VENTAS) ---
            if ($isVentaType && method_exists($this, 'revertStockForSale')) {
                $this->revertStockForSale($sale);
            }

            // --- 3b. REVERTIR EL DESCUENTO DE PIEZAS DE "MUESTRAS Y REGALOS" ---
            if ($isMuestraType) {
                $this->revertMuestraStockForSale($sale);
            }

            // --- 4. ACTUALIZAR LA ORDEN ---
            $updateData = [
                'oce_name' => $validated['oce_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'currency' => $validated['currency'] ?? null,
                'is_high_priority' => $validated['is_high_priority'],
                // En 'muestra' no aplican precios bajos ni costo de herramental.
                'has_low_price' => $isMuestraType ? false : ($validated['has_low_price'] ?? false),
                'tooling_cost' => $isMuestraType ? null : ($validated['tooling_cost'] ?? null),
                'promise_date' => $firstPromiseDate,
                'branch_id' => $validated['branch_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'quote_id' => $isMuestraType ? null : ($validated['quote_id'] ?? null),
                'order_via' => $validated['order_via'] ?? null,
                'freight_option' => $validated['freight_option'] ?? null,
                'freight_cost' => $validated['freight_cost'] ?? 0,
                'shipping_option' => $validated['shipping_option'] ?? null,
                'total_amount' => $isSaleType ? array_reduce($validated['products'], function ($carry, $product) {
                    return $carry + ($product['quantity'] * ($product['price'] ?? 0));
                }, 0) : 0,
            ];

            // Validar si debemos resetear el estatus a "Pendiente"
            if (in_array($sale->status, ['Pendiente', 'Autorizada'])) {
                $updateData['status'] = 'Pendiente';
                $updateData['authorized_at'] = null;
                $updateData['authorized_user_name'] = null;
            }

            $sale->update($updateData);

            // --- 5. SINCRONIZAR PRODUCTOS DE LA ORDEN ---
            $productIdsFromRequest = collect($validated['products'])->pluck('id');
            $sale->saleProducts()->whereNotIn('product_id', $productIdsFromRequest)->delete();

            foreach ($validated['products'] as $productData) {
                $sale->saleProducts()->updateOrCreate(
                    ['product_id' => $productData['id']],
                    [
                        'quantity' => $productData['quantity'],
                        'price' => $productData['price'] ?? 0,
                        'notes' => $productData['notes'] ?? null,
                        'customization_details' => $productData['customization_details'] ?? null,
                        'has_low_price' => $isMuestraType ? false : ($productData['has_low_price'] ?? false), // AGREGADO
                        'low_price_reason' => $isMuestraType ? null : ($productData['low_price_reason'] ?? null), // AGREGADO
                    ]
                );
            }
            
            // --- 6. SINCRONIZAR ENVÍOS Y SUS PRODUCTOS (SOLO PARA VENTAS) ---
            if ($isSaleType) {
                // Forzamos la recarga de la relación para asegurar que tenemos los datos más frescos
                $sale->load('saleProducts');
                // Creamos un mapa para encontrar fácilmente el 'sale_product_id' a partir del 'product_id'
                $saleProductsMap = $sale->saleProducts()->pluck('id', 'product_id');

                $incomingShipmentIds = collect($validated['shipments'])->pluck('id')->filter();
                
                // Eliminar envíos que ya no están en la petición
                $sale->shipments()->whereNotIn('id', $incomingShipmentIds)->delete();

                foreach ($validated['shipments'] as $shipmentData) {
                    // Actualizar o crear el envío
                    $shipment = $sale->shipments()->updateOrCreate(
                        ['id' => $shipmentData['id'] ?? null],
                        [
                            'status' => $shipmentData['status'] ?? 'Pendiente',
                            'promise_date' => $shipmentData['promise_date'] ?? null,
                            'shipping_company' => $shipmentData['shipping_company'] ?? null,
                            'tracking_guide' => $shipmentData['tracking_guide'] ?? null,
                        ]
                    );

                    // Sincronizar los productos de este envío (MANUALMENTE para relaciones HasMany)
                    $saleProductIdsInRequest = [];
                    $productSyncData = [];

                    // 1. Preparar los datos y los IDs de la petición actual
                    foreach($shipmentData['products'] as $productShipmentData) {
                        $saleProductId = $saleProductsMap->get($productShipmentData['product_id']);
                        // Solo procesar si el producto existe en la orden y la cantidad es positiva
                        if ($saleProductId && $productShipmentData['quantity'] > 0) {
                            $saleProductIdsInRequest[] = $saleProductId;
                            $productSyncData[$saleProductId] = ['quantity' => $productShipmentData['quantity']];
                        }
                    }

                    // 2. Eliminar los productos que se quitaron de este envío
                    $shipment->shipmentProducts()->whereNotIn('sale_product_id', $saleProductIdsInRequest)->delete();

                    // 3. Actualizar o crear los productos que están en el envío
                    foreach ($productSyncData as $saleProdId => $data) {
                        $shipment->shipmentProducts()->updateOrCreate(
                            ['sale_product_id' => $saleProdId],  // Condición para buscar
                            ['quantity' => $data['quantity']]   // Datos para actualizar o crear
                        );
                    }
                }
            }

            // --- 6b. RE-APLICAR EL DESCUENTO DE PIEZAS DE "MUESTRAS Y REGALOS" ---
            if ($isMuestraType) {
                $this->applyMuestraStockForSale($sale);
            }

            // --- 7. RE-APLICAR LÓGICA DE INVENTARIO (COMO EN EL STORE) ---
            // Las órdenes de muestra/regalo no mueven inventario ni generan producción.
            if ($isVentaType) {
                foreach ($validated['products'] as $productData) {
                    // CORRECCIÓN: Cargar parent.components.storages igual que en store
                    $product = Product::with(['storages', 'components.storages', 'parent.components.storages'])->find($productData['id']);
                    $saleProduct = $sale->saleProducts()->where('product_id', $product->id)->first();
                    $quantityInSale = $productData['quantity'];
                    
                    $stockFinishedProduct = $product->storages->first()?->quantity ?? 0;

                    $takenFromStock = min($quantityInSale, $stockFinishedProduct);
                    $quantityToProduce = $quantityInSale - $takenFromStock;
                    
                    $saleProduct->update(['quantity_to_produce' => $quantityToProduce]);
                    
                    if ($takenFromStock > 0) {
                        $storage = $product->storages->first();
                        $storage->decrement('quantity', $takenFromStock);
                        StockMovement::create([
                            'product_id' => $product->id,
                            'storage_id' => $storage->id,
                            'quantity_change' => $takenFromStock,
                            'type' => 'Salida',
                            'notes' => "Descuento por Orden de venta #{$sale->id} (Actualizado)"
                        ]);
                    }

                    // Lógica para descontar componentes (si aplica)
                    // CORRECCIÓN: Igual que en store(), ahora se descuentan los componentes de la
                    // cantidad COMPLETA de la orden (no solo lo que falta por producir) y se registra
                    // el movimiento, aunque el producto terminado tenga stock.
                    if ($product->actual_components->isNotEmpty()) {
                        foreach ($product->actual_components as $component) {
                            $requiredQuantity = $component->pivot->quantity * $quantityInSale;
                            $componentStorage = $component->storages->first();
                            
                            if ($componentStorage && $componentStorage->quantity > 0) {
                                $discountQuantity = min($requiredQuantity, $componentStorage->quantity);
                                
                                $componentStorage->decrement('quantity', $discountQuantity);
                                
                                StockMovement::create([
                                    'product_id' => $component->id,
                                    'storage_id' => $componentStorage->id,
                                    'quantity_change' => $discountQuantity,
                                    'type' => 'Salida',
                                    'notes' => "Descuento de componentes para {$quantityInSale} de {$product->name} (Orden #{$sale->id}, Actualizado)"
                                ]);
                            }
                        }
                    }
                }
            }
            
            // --- 8. MANEJAR ARCHIVOS ADJUNTOS ---
            if ($request->hasFile('oce_media')) {
                $sale->addMultipleMediaFromRequest(['oce_media'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('oce_media');
                });
            }
            
            DB::commit();

            // --------------------------------------------------------------------------
            // ---> NUEVO: DESPACHAR EL JOB PARA VERIFICAR STOCK Y NOTIFICAR
            // Se vuelve a ejecutar en update porque los movimientos pudieron alterar el stock
            // NOTA: en órdenes de muestra/regalo no hay movimientos de inventario, no aplica.
            // --------------------------------------------------------------------------
            if (!$isMuestraType) {
                CheckLowStockAndNotifyJob::dispatch($sale);
            }
            // <--- FIN NUEVO

            Log::info("Órden #{$sale->id} (tipo: {$sale->type}) actualizada por el usuario " . auth()->id());
            return redirect()->route('sales.show', $sale->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar la órden #{$sale->id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->withInput()->withErrors(['generic_error' => 'Ocurrió un error inesperado al actualizar la orden.']);
        }
    }

    /**
     * Elimina una orden de venta y revierte el stock comprometido.
     */
    public function destroy(Sale $sale)
    {
        DB::beginTransaction();
        try {
            // Solo revertimos stock para las órdenes de tipo 'venta'
            if ($sale->type === 'venta') {
                $this->revertStockForSale($sale);
            }

            // Si la orden se creó desde una cotización, limpiar el sale_id en la cotización
            if ($sale->quote_id) {
                Quote::where('id', $sale->quote_id)->update(['sale_id' => null]);
            }

            // Si la orden provino de un seguimiento de muestra, liberar el vínculo
            SampleTracking::where('sale_id', $sale->id)->update(['sale_id' => null]);

            // Revertir las piezas de "Muestras y regalos" comprometidas por la orden
            if ($sale->type === 'muestra') {
                $this->revertMuestraStockForSale($sale);
            }

            // Eliminar la orden y sus relaciones
            $sale->delete();

            DB::commit();
            
            Log::info("Órden #{$sale->id} eliminada por el usuario " . auth()->id());
            return redirect()->route('sales.index')->with('success', 'Órden eliminada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar la órden #{$sale->id}: " . $e->getMessage());
            return back()->withErrors(['generic_error' => 'Ocurrió un error al eliminar la orden.']);
        }
    }

    /**
     * Elimina masivamente las órdenes de venta y revierte el stock comprometido para cada una.
     */
    public function massiveDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:sales,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->ids as $id) {
                $sale = Sale::find($id);
                if ($sale) {
                    // Solo revertimos stock para las órdenes de tipo 'venta'
                    if ($sale->type === 'venta') {
                        $this->revertStockForSale($sale);
                    }
                    // Si la orden se creó desde una cotización, limpiar el sale_id en la cotización
                    if ($sale->quote_id) {
                        Quote::where('id', $sale->quote_id)->update(['sale_id' => null]);
                    }
                    // Si la orden provino de un seguimiento de muestra, liberar el vínculo
                    SampleTracking::where('sale_id', $sale->id)->update(['sale_id' => null]);
                    // Revertir las piezas de "Muestras y regalos" comprometidas por la orden
                    if ($sale->type === 'muestra') {
                        $this->revertMuestraStockForSale($sale);
                    }
                    $sale->delete();
                }
            }

            DB::commit();

            Log::info("Eliminación masiva de órdenes por el usuario " . auth()->id() . ". IDs: " . implode(', ', $request->ids));
            return redirect()->route('sales.index')->with('success', 'Órdenes eliminadas exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error en la eliminación masiva de órdenes: " . $e->getMessage());
            return back()->withErrors(['generic_error' => 'Ocurrió un error al eliminar las órdenes.']);
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $sales = Sale::with([
                'user:id,name', 
                'branch', 
                'saleProducts.product:id,name,cost',
                'quote:id,root_quote_id',
                // Agregamos las relaciones necesarias para el tooltip de cambios
                'productExchanges.returnedProduct:id,name',
                'productExchanges.newProduct:id,name',
                'shipments:id,sale_id,status,shipping_company,tracking_guide,promise_date'
            ])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhere('status', 'like', "%{$query}%")
                ->orWhereHas('user', function ($parentQuery) use ($query) {
                    $parentQuery->where('name', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->select('id', 'currency', 'branch_id', 'quote_id', 'user_id', 'type', 'status', 'total_amount', 'created_at', 'is_high_priority', 'authorized_user_name', 'authorized_at', 'pre_invoice_folio', 'stamped_invoice_folio', 'billing_status', 'promise_date')
            ->get();

        // Misma lógica del index: root_quote_id para mostrar, active version para enlazar
        $quoteIds = $sales->pluck('quote_id')->unique()->filter();
        if ($quoteIds->isNotEmpty()) {
            $quotesData = Quote::whereIn('id', $quoteIds)
                ->select('id', 'root_quote_id')
                ->get()
                ->keyBy('id');

            $rootIds = $quotesData->pluck('root_quote_id')->unique()->filter();
            $activeVersions = collect();
            if ($rootIds->isNotEmpty()) {
                $activeVersions = Quote::whereIn('root_quote_id', $rootIds)
                    ->where('is_active', true)
                    ->select('id', 'root_quote_id')
                    ->orderBy('version', 'desc')
                    ->get()
                    ->keyBy('root_quote_id');
            }

            $sales->each(function ($sale) use ($quotesData, $activeVersions) {
                if ($sale->quote_id && isset($quotesData[$sale->quote_id])) {
                    $rootId = $quotesData[$sale->quote_id]->root_quote_id;
                    $sale->quote_root_id = $rootId;
                    $sale->quote_active_id = $activeVersions->get($rootId)?->id ?? $sale->quote_id;
                }
            });
        }

        // Seguimiento de muestra vinculado (para órdenes de tipo muestra/regalo: MUE-xxxx)
        $sampleTrackingBySale = SampleTracking::whereIn('sale_id', $sales->pluck('id'))
            ->select('id', 'sale_id')
            ->get()
            ->keyBy('sale_id');

        $sales->each(function ($sale) use ($sampleTrackingBySale) {
            $sale->sample_tracking_id = $sampleTrackingBySale->get($sale->id)?->id;
        });

        return response()->json(['items' => $sales], 200);
    }

    public function authorizeSale(Sale $sale)
    {
        $sale->update([
            'authorized_user_name' => auth()->user()->name,
            'authorized_at' => now(),
            'status' => 'Autorizada',
        ]);

        $sale->load('user');

        // Notificar al creador de la orden si quien autoriza no es el mismo usuario
        if (auth()->id() != $sale->user->id) {
            // Generamos un folio legible para la notificación
            $sale_folio = 'OV-' . str_pad($sale->id, 4, "0", STR_PAD_LEFT);
            
            // Enviamos la notificación al usuario que creó la venta
            $sale->user->notify(new SaleAuthorizedNotification(
                'Orden autorizada', // Título de la notificación
                $sale_folio, // Folio para mostrar
                $sale->type, // El tipo de orden ('venta' o 'stock')
                route('sales.show', $sale->id) // URL para redirigir al usuario
            ));
        }
        // --- FIN: LÓGICA DE NOTIFICACIÓN ACTUALIZADA ---

        return response()->json(['message' => 'Orden autorizada', 'item' => $sale]);
    }

    /**
     * Muestra una vista de impresión para la orden de venta.
     *
     * @param  \App\Models\Sale  $sale
     * @return \Inertia\Response
     */
    public function print(Sale $sale)
    {
        $sale->load([
            'branch:id,name',
            'branch.contacts:id,name', // Sucursal del cliente y sus contactos
            'user:id,name',            // Usuario que creó la venta
            'saleProducts.product:id,name,code,measure_unit', // Productos de la venta y su info del catálogo
            'saleProducts.product.media', // Productos de la venta y su info del catálogo
            'shipments.shipmentProducts'       // Envíos o parcialidades de la venta
        ]);

        // return $sale;
        return Inertia::render('Sale/Print', [
            'sale' => $sale
        ]);
    }

    /**
     * Recupera todas las ventas (id, nombre de cliente) para mostrarla en el show de ventas.
     *
     * @param  \App\Models\Sale  $sale
     * @return \Inertia\Response
     */
    public function fetchAll()
    {
        $sales = Sale::with('branch:id,name')
                    ->select('id', 'branch_id', 'type')
                    ->orderBy('id', 'desc')
                    ->take(200)
                    ->get()
                    ->map(function ($sale) {
                        return [
                            'id' => $sale->id,
                            'name' => (($sale->type !== 'stock') ? 'OV-' : 'OS-') . str_pad($sale->id, 4, "0", STR_PAD_LEFT) . ' - ' . ($sale->branch ? $sale->branch->name : 'Sin cliente'),
                        ];
                    });
        return response()->json($sales);
    }

    /**
     * Método privado para revertir el stock de una orden de venta.
     * Incrementa el stock de productos terminados y componentes.
     */
    private function revertStockForSale(Sale $sale)
    {
        // Carga las relaciones necesarias para acceder a los datos de stock
        // CORRECCIÓN: se cargan también los componentes del padre (parent.components.storages)
        // para que las variantes hereden correctamente los componentes al revertir.
        $sale->load('saleProducts.product.components.storages', 'saleProducts.product.parent.components.storages', 'saleProducts.product.storages');

        foreach ($sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;
            $totalQuantity = $saleProduct->quantity;
            $quantityToProduce = $saleProduct->quantity_to_produce;

            // 1. Revertir stock de producto terminado (solo la parte que se tomó de stock)
            $takenFromStock = $totalQuantity - $quantityToProduce;
            if ($takenFromStock > 0 && $product->storages->first()) {
                $storage = $product->storages->first();
                $storage->increment('quantity', $takenFromStock);
                
                StockMovement::create([
                    'product_id' => $product->id,
                    'storage_id' => $storage->id,
                    'quantity_change' => $takenFromStock, // Positivo para devolver
                    'type' => 'Entrada',
                    'notes' => "Reversión por " . $sale->type === 'venta' ? "OV-" : "OS-" . $sale->id 
                ]);
            }

            // 2. Revertir stock de componentes (cantidad completa de la orden, ya que en
            // store/update ahora se descuentan los componentes de la cantidad total vendida).
            // CORRECCIÓN: se usa actual_components para soportar variantes que heredan
            // componentes del producto padre.
            $components = $product->actual_components;
            if ($components->isNotEmpty()) {
                foreach ($components as $component) {
                    $requiredQuantity = $component->pivot->quantity * $totalQuantity;
                    if ($component->storages->first()) {
                        $componentStorage = $component->storages->first();
                        $componentStorage->increment('quantity', $requiredQuantity);

                        StockMovement::create([
                            'product_id' => $component->id,
                            'storage_id' => $componentStorage->id,
                            'quantity_change' => $requiredQuantity, // Positivo para devolver
                            'type' => 'Entrada',
                            'notes' => "Reversión de material para Orden #{$sale->id}"
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Descuenta del stock las piezas de los productos de la categoría "Muestras y regalos"
     * en una orden de tipo muestra/regalo. No se genera producción: solo se comprometen
     * las piezas disponibles del inventario.
     *
     * quantity_to_produce guarda la parte que NO había en stock (permite revertir exacto).
     */
    private function applyMuestraStockForSale(Sale $sale): void
    {
        $sale->load('saleProducts.product.storages');

        foreach ($sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;

            if (!$product || $product->product_type !== MuestraProductService::PRODUCT_TYPE) {
                continue; // Los productos de catálogo no mueven inventario en una muestra
            }

            $storage = $product->storages->first();
            $deducted = 0;

            if ($storage && $storage->quantity > 0) {
                $deducted = min($saleProduct->quantity, $storage->quantity);

                if ($deducted > 0) {
                    $storage->decrement('quantity', $deducted);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'storage_id' => $storage->id,
                        'quantity_change' => $deducted,
                        'type' => 'Salida',
                        'notes' => "Descuento por Orden de Venta (Muestra/Regalo) #{$sale->id}",
                    ]);
                }
            }

            $saleProduct->update(['quantity_to_produce' => $saleProduct->quantity - $deducted]);
        }
    }

    /**
     * Revierte el descuento de stock de las piezas de "Muestras y regalos" de una orden.
     */
    private function revertMuestraStockForSale(Sale $sale): void
    {
        $sale->load('saleProducts.product.storages');

        foreach ($sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;

            if (!$product || $product->product_type !== MuestraProductService::PRODUCT_TYPE) {
                continue;
            }

            $deducted = $saleProduct->quantity - $saleProduct->quantity_to_produce;

            if ($deducted > 0) {
                $storage = $product->storages->first();

                if ($storage) {
                    $storage->increment('quantity', $deducted);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'storage_id' => $storage->id,
                        'quantity_change' => $deducted,
                        'type' => 'Entrada',
                        'notes' => "Reversión por Orden de Venta (Muestra/Regalo) #{$sale->id}",
                    ]);
                }
            }
        }
    }

    public function branchSales(Branch $branch)
    {
        // Carga las ventas con las relaciones necesarias para la tabla
        $sales = $branch->sales()
            ->with([
                'user:id,name', 
                'saleProducts.product:id,name,cost', // Necesario para calcular utilidad (append)
                'invoice:id,folio,sale_id',          // Para mostrar folio de factura
                // --- Relaciones para el indicador de cambios ---
                'productExchanges.returnedProduct:id,name',
                'productExchanges.newProduct:id,name'
            ])
            ->latest() // Ordena por las más recientes
            ->take(20) // Limita a las 20 más recientes
            ->get([
                'id', 
                'branch_id', 
                'quote_id', 
                'user_id', 
                'invoice_id', // Agregado: necesario para v-if="scope.row.invoice_id"
                'type', 
                'status', 
                'total_amount', 
                'currency',   // Agregado: necesario para mostrar el símbolo correcto
                'created_at', 
                'authorized_user_name', 
                'authorized_at'
            ]);

        return response()->json($sales);
    }

    public function qualityCertificate(Sale $sale)
    {
       // Carga todas las relaciones necesarias para evitar consultas N+1
        $sale->load(['branch', 'contact', 'saleProducts.product']);

        return inertia('Sale/QualityCertificate', [
            'sale' => $sale
        ]);
    }

    public function clone(Sale $sale)
    {
        DB::beginTransaction();

        try {
            // 1. REPLICAR LA ORDEN PRINCIPAL
            $newSale = $sale->replicate();
            $newSale->status = 'Pendiente';
            $newSale->user_id = auth()->id();
            $newSale->authorized_at = null;
            $newSale->authorized_user_name = null;
            $newSale->created_at = now();
            $newSale->updated_at = now();
            
            // Opcional: Agregar indicador de copia en notas u OCE name
            // $newSale->notes = $newSale->notes . " (Copia de #{$sale->id})";

            $newSale->save();

            // Mapa para relacionar ID producto original -> ID nuevo sale_product (para envíos)
            $productToNewSaleProductId = [];

            // 2. REPLICAR PRODUCTOS Y RECALCULAR STOCK
            // Cargamos los productos originales
            $originalProducts = $sale->saleProducts;

            foreach ($originalProducts as $originalItem) {
                // Crear copia del item
                $newItem = $originalItem->replicate();
                $newItem->sale_id = $newSale->id;
                $newItem->quantity_produced = 0;
                $newItem->quantity_shipped = 0;
                // Inicialmente ponemos todo para producir, la lógica de abajo ajustará esto
                $newItem->quantity_to_produce = $newItem->quantity; 
                $newItem->created_at = now();
                $newItem->updated_at = now();
                $newItem->save();

                $productToNewSaleProductId[$originalItem->product_id] = $newItem->id;

                // --- LÓGICA DE STOCK (Idéntica a store) ---
                // Determinar si es venta, stock o muestra para ajustar la lógica
                $isSaleType = $newSale->type === 'venta';
                $isMuestraType = $newSale->type === 'muestra';

                $product = Product::with(['storages', 'components.storages', 'parent.components.storages'])->find($newItem->product_id);
                $quantityInTransaction = $newItem->quantity;
                $quantityToProduce = 0;

                if ($isSaleType) {
                    $stockFinishedProduct = $product->storages->first()?->quantity ?? 0;

                    // Calcular cuánto se toma de stock y cuánto se produce
                    $takenFromStock = min($quantityInTransaction, $stockFinishedProduct);
                    $quantityToProduce = $quantityInTransaction - $takenFromStock;
                    
                    // Actualizar el item con lo que realmente falta por producir
                    $newItem->update(['quantity_to_produce' => $quantityToProduce]);

                    // Descontar stock del producto terminado
                    if ($takenFromStock > 0) {
                        $storage = $product->storages->first();
                        $storage->decrement('quantity', $takenFromStock);
                        
                        StockMovement::create([
                            'product_id' => $product->id,
                            'storage_id' => $storage->id,
                            'quantity_change' => $takenFromStock,
                            'type' => 'Salida',
                            'notes' => "Descuento por Orden de venta (Clon) #{$newSale->id}"
                        ]);
                    }
                } elseif ($isMuestraType) {
                    // Las órdenes de muestra/regalo no mueven inventario ni generan producción.
                    $quantityToProduce = 0;
                    $newItem->update(['quantity_to_produce' => 0]);
                } else {
                    // Si es tipo stock, todo es para producir
                    $quantityToProduce = $quantityInTransaction;
                }

                // Descontar stock de componentes (materia prima)
                // CORRECCIÓN: igual que en store(), se descuentan los componentes de la cantidad
                // completa y se usa actual_components para soportar variantes que heredan
                // componentes del producto padre.
                if (!$isMuestraType && $product->actual_components->isNotEmpty()) {
                    foreach ($product->actual_components as $component) {
                        $requiredQuantity = $component->pivot->quantity * $quantityInTransaction;
                        $componentStorage = $component->storages->first();

                        if ($componentStorage) {
                            $currentStock = $componentStorage->quantity;
                            $discountQuantity = min($requiredQuantity, $currentStock);

                            $componentStorage->update(['quantity' => max(0, $currentStock - $requiredQuantity)]);

                            if ($discountQuantity > 0) {
                                $notes = $isSaleType 
                                    ? "Descuento de componentes para {$quantityInTransaction} de {$product->name} (Orden #{$newSale->id})"
                                    : "Descuento de componentes para {$quantityInTransaction} de {$product->name} para stock (Clon).";

                                StockMovement::create([
                                    'product_id' => $component->id,
                                    'storage_id' => $componentStorage->id,
                                    'quantity_change' => $discountQuantity,
                                    'type' => 'Salida',
                                    'notes' => $notes
                                ]);
                            }
                        }
                    }
                }
            }

            // 2b. DESCONTAR LAS PIEZAS DE "MUESTRAS Y REGALOS" DE LA COPIA
            if ($newSale->type === 'muestra') {
                $this->applyMuestraStockForSale($newSale);
            }

            // 3. REPLICAR ENVÍOS (Solo si existen)
            if ($sale->shipments->isNotEmpty()) {
                foreach ($sale->shipments as $shipment) {
                    $newShipment = $shipment->replicate();
                    $newShipment->sale_id = $newSale->id;
                    $newShipment->status = 'Pendiente';
                    $newShipment->tracking_guide = null; // Limpiamos guía por si acaso
                    $newShipment->created_at = now();
                    $newShipment->updated_at = now();
                    $newShipment->save();

                    // Replicar productos del envío
                    foreach ($shipment->shipmentProducts as $sp) {
                        // Necesitamos saber qué producto era para buscar su nuevo ID en la nueva venta
                        // Asumimos que shipmentProduct tiene relación 'saleProduct' definida
                        $originalProductId = $sp->saleProduct->product_id;

                        if (isset($productToNewSaleProductId[$originalProductId])) {
                            $newSp = $sp->replicate();
                            $newSp->shipment_id = $newShipment->id;
                            $newSp->sale_product_id = $productToNewSaleProductId[$originalProductId];
                            $newSp->created_at = now();
                            $newSp->updated_at = now();
                            $newSp->save();
                        }
                    }
                }
            }

            DB::commit();
            
            Log::info("Órden #{$sale->id} clonada exitosamente a nueva orden #{$newSale->id} por usuario " . auth()->id());

            // Redirigir a la vista de edición o detalle de la nueva venta
            return redirect()->route('sales.show', $newSale->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al clonar la orden {$sale->id}: " . $e->getMessage());
            return back()->withErrors(['error' => 'Ocurrió un error al clonar la orden: ' . $e->getMessage()]);
        }
    }
}
