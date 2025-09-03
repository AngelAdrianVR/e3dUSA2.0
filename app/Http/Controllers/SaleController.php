<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\StockMovement;
use App\Notifications\SaleAuthorizedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        // Determina si se deben mostrar todas las ventas o solo las del usuario.
        // El frontend enviará un query param `view=all` para ver todas.
        $showAll = $request->query('view') === 'all';

        $query = Sale::query();

        // Si no se solicita ver todo, filtra por el usuario autenticado.
        if (!$showAll) {
            $query->where('user_id', Auth::id());
        }

        $sales = $query->with(['user:id,name', 'branch:id,name', 'saleProducts.product:id,name,cost'])
                    ->select('id', 'branch_id', 'quote_id', 'user_id', 'type', 'status', 'total_amount', 'created_at', 'is_high_priority', 'authorized_user_name', 'authorized_at', 'created_at')
                    ->latest() // Ordena por los más recientes primero
                    ->paginate(15) // Pagina los resultados
                    ->withQueryString(); // Mantiene los query params (ej. `view=all`) en la paginación
        
        // Retorna la vista de Inertia con los datos paginados.
        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => $request->only(['view']), // Pasa los filtros actuales a la vista
        ]);
    }

    public function create(Request $request)
    {
        // Valida que el quote_id, si existe, sea un número
        $request->validate([
            'quote_id' => 'nullable|integer|exists:quotes,id'
        ]);

        // Obtiene el quote_id de la solicitud
        $quoteToConvertId = $request->input('quote_id');

        // Obtenemos todas las sucursales (clientes) activas.
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Obtenemos solo las cotizaciones que han sido autorizadas y no están en una OV.
        $quotes = Quote::where('authorized_at', '!=', null)
                    ->latest()
                    ->whereDoesntHave('sale')
                    ->select('id', 'branch_id', 'sale_id')
                    ->with('branch:id,name')
                    ->get();
        
        return Inertia::render('Sale/Create', [
            'branches' => $branches,
            'quotes' => $quotes,
            'catalog_products' => Product::where('product_type', 'Catálogo')->select('id', 'name')->get(),
            // Pasa el ID de la cotización a convertir como un prop
            'quoteToConvertId' => $quoteToConvertId,
        ]);
    }

    public function store(Request $request)
    {
        // --- 1. DETERMINAR TIPO Y REGLAS BASE ---
        $isSaleType = $request->input('type') === 'venta';

        $rules = [
            'type' => ['required', Rule::in(['venta', 'stock'])],
            'oce_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_high_priority' => 'required|boolean',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
            'oce_media' => 'nullable|array|max:3',
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:2048',
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
            
            $rules['products.*.price'] = ['required', 'numeric', 'min:0'];

            $rules['shipments'] = ['required', 'array', 'min:1'];
            $rules['shipments.*.promise_date'] = ['nullable', 'date'];
            $rules['shipments.*.shipping_company'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.tracking_guide'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.acknowledgement_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:2048'];
            $rules['shipments.*.products'] = ['required', 'array'];
            $rules['shipments.*.products.*.product_id'] = ['required', 'exists:products,id'];
            $rules['shipments.*.products.*.quantity'] = ['required', 'integer', 'min:0'];
        } else {
            // Regla para 'stock': el precio no es requerido, pero si se envía debe ser numérico.
            $rules['products.*.price'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

        // --- NUEVO: OBTENER LA FECHA PROMESA DEL PRIMER ENVÍO ---
        $firstPromiseDate = ($isSaleType && !empty($validated['shipments'])) ? ($validated['shipments'][0]['promise_date'] ?? null) : null;

        DB::beginTransaction();

        try {
            // --- 3. CREAR LA ORDEN (VENTA O STOCK) ---
            $sale = Sale::create([
                'type' => $validated['type'],
                'user_id' => auth()->id(),
                'status' => 'Pendiente',
                'oce_name' => $validated['oce_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_high_priority' => $validated['is_high_priority'],
                'promise_date' => $firstPromiseDate, // Se asigna la fecha del primer envío
                
                // Campos que son nulos para 'stock'
                'branch_id' => $validated['branch_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'quote_id' => $validated['quote_id'] ?? null,
                'order_via' => $validated['order_via'] ?? null,
                'freight_option' => $validated['freight_option'] ?? null,
                'freight_cost' => $validated['freight_cost'] ?? 0,
                'shipping_option' => $validated['shipping_option'] ?? null,
                
                'total_amount' => $isSaleType ? array_reduce($validated['products'], function ($carry, $product) {
                    return $carry + ($product['quantity'] * ($product['price'] ?? 0));
                }, 0) : 0,
            ]);
            
            if ($isSaleType && isset($validated['quote_id'])) {
                Quote::find($validated['quote_id'])->update([
                    'sale_id' => $sale->id,
                    'status' => 'Aceptada'
                ]);
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
                    'quantity_produced' => 0,
                    'quantity_shipped' => 0,
                    'quantity_to_produce' => $productData['quantity'],
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

            // --- 6. LÓGICA DE INVENTARIO Y PRODUCCIÓN (SOLO PARA VENTAS) ---
            if ($isSaleType) {
                foreach ($validated['products'] as $productData) {
                    $product = Product::with(['storages', 'components.storages'])->find($productData['id']);
                    $saleProduct = SaleProduct::where('sale_id', $sale->id)->where('product_id', $product->id)->first();
                    $quantityInSale = $productData['quantity'];
                    
                    $stockFinishedProduct = $product->storages->first()?->quantity ?? 0;

                    // 6.1 Calcular cuánto se toma de stock y cuánto se produce
                    $takenFromStock = min($quantityInSale, $stockFinishedProduct);
                    $quantityToProduce = $quantityInSale - $takenFromStock;
                    
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

                    // 6.3 Descontar stock de los componentes (solo para lo que se va a producir)
                    if ($quantityToProduce > 0 && $product->components->isNotEmpty()) {
                        foreach ($product->components as $component) {
                            $requiredQuantity = $component->pivot->quantity * $quantityToProduce;
                            $componentStorage = $component->storages->first();

                            if ($componentStorage) {
                                $currentStock = $componentStorage->quantity;
                                $discountQuantity = min($requiredQuantity, $currentStock);

                                $componentStorage->update(['quantity' => max(0, $currentStock - $requiredQuantity)]);

                                if ($discountQuantity > 0) {
                                    StockMovement::create([
                                        'product_id' => $component->id,
                                        'storage_id' => $componentStorage->id,
                                        'quantity_change' => $discountQuantity,
                                        'type' => 'Salida',
                                        'notes' => "Descuento para producir {$quantityToProduce} de {$product->name} (Orden #{$sale->id})"
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            
            // --- 7. MANEJAR ARCHIVOS ADJUNTOS ---
            if ($request->hasFile('oce_media')) {
                $sale->addMultipleMediaFromRequest(['oce_media'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('oce_media');
                });
            }

            DB::commit();

            Log::info("Órden #{$sale->id} (tipo: {$sale->type}) creada por el usuario " . auth()->id());

            return redirect()->route('sales.index')->with('success', 'Órden creada exitosamente.');

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
            'branch',
            'media',
            'user:id,name',
            'saleProducts.product.media',
            'saleProducts.product.priceHistory' => function ($q) {
                $q->orderBy('created_at', 'desc');
            },
            'shipments',
            'contact'
        ]);

        // return $sale;
        return Inertia::render('Sale/Show', [
            'sale' => $sale
        ]);
    }

    public function edit(Sale $sale)
    {
        // Obtenemos todas las sucursales (clientes) activas.
        $branches = Branch::select('id', 'name')->with('contacts')->get();

        // Obtenemos solo las cotizaciones que han sido autorizadas y no están en una OV.
        $quotes = Quote::where('authorized_at', '!=', null)
                    ->latest()
                    ->whereDoesntHave('sale')
                    ->select('id', 'branch_id', 'sale_id')
                    ->with('branch:id,name')
                    ->get();

        return Inertia::render('Sale/Edit', [
            'branches' => $branches,
            'quotes' => $quotes,
            'catalog_products' => Product::where('product_type', 'Catálogo')->select('id', 'name')->get(),
            'sale' => $sale->load(['branch.contacts', 'saleProducts.product.media', 'shipments.shipmentProducts.saleProduct.product', 'media']),
        ]);
    }

    public function update(Request $request, Sale $sale)
    {
        // --- 1. DETERMINAR TIPO Y REGLAS BASE ---
        $isSaleType = $sale->type === 'venta';

        $rules = [
            'oce_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_high_priority' => 'required|boolean',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.notes' => 'nullable|string',
            'products.*.customization_details' => 'nullable|array',
            'oce_media' => 'nullable|array|max:3',
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:2048',
        ];

        if ($isSaleType) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
            $rules['contact_id'] = ['required', 'exists:contacts,id'];
            $rules['quote_id'] = ['nullable', 'exists:quotes,id'];
            $rules['order_via'] = ['nullable', 'string', 'max:255'];
            $rules['freight_option'] = ['required', 'string', 'max:255'];
            $rules['freight_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['shipping_option'] = ['required', 'string'];
            $rules['promise_date'] = ['nullable', 'date'];

            $rules['products.*.price'] = ['required', 'numeric', 'min:0'];

            $rules['shipments'] = ['required', 'array', 'min:1'];
            $rules['shipments.*.promise_date'] = ['nullable', 'date'];
            $rules['shipments.*.shipping_company'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.tracking_guide'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.acknowledgement_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt,webp|max:2048'];
            $rules['shipments.*.products'] = ['required', 'array'];
            $rules['shipments.*.products.*.product_id'] = ['required', 'exists:products,id'];
            $rules['shipments.*.products.*.quantity'] = ['required', 'integer', 'min:0'];
        } else {
            $rules['products.*.price'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {

            // --- 2. REVERTIR MOVIMIENTOS DE STOCK ANTERIORES (SI ES VENTA) ---
            if ($isSaleType) {
                $this->revertStockForSale($sale);
            }

            // --- 3. ACTUALIZAR LA ORDEN ---
            $sale->update([
                'oce_name' => $validated['oce_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'is_high_priority' => $validated['is_high_priority'],
                'branch_id' => $validated['branch_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'quote_id' => $validated['quote_id'] ?? null,
                'order_via' => $validated['order_via'] ?? null,
                'freight_option' => $validated['freight_option'] ?? null,
                'freight_cost' => $validated['freight_cost'] ?? 0,
                'promise_date' => $validated['promise_date'] ?? 0,
                'shipping_option' => $validated['shipping_option'] ?? null,
                'total_amount' => $isSaleType ? array_reduce($validated['products'], function ($carry, $product) {
                    return $carry + ($product['quantity'] * ($product['price'] ?? 0));
                }, 0) : 0,
            ]);

            // --- 4. SINCRONIZAR PRODUCTOS Y RECALCULAR STOCK ---
            // Eliminar productos que ya no están en la petición
            $productIdsFromRequest = collect($validated['products'])->pluck('id');
            $sale->saleProducts()->whereNotIn('product_id', $productIdsFromRequest)->delete();

            foreach ($validated['products'] as $productData) {
                // Actualizar o crear el producto en la venta
                $saleProduct = $sale->saleProducts()->updateOrCreate(
                    ['product_id' => $productData['id']],
                    [
                        'quantity' => $productData['quantity'],
                        'price' => $productData['price'] ?? 0,
                        'notes' => $productData['notes'] ?? null,
                        'customization_details' => $productData['customization_details'] ?? null,
                    ]
                );

                // --- 5. RE-APLICAR LÓGICA DE INVENTARIO (COMO EN EL STORE) ---
                if ($isSaleType) {
                    $product = Product::with(['storages', 'components.storages'])->find($productData['id']);
                    $quantityInSale = $productData['quantity'];
                    $stockFinishedProduct = $product->storages->first()?->quantity ?? 0;

                    // 5.1 Recalcular cuánto se toma y cuánto se produce
                    $takenFromStock = min($quantityInSale, $stockFinishedProduct);
                    $quantityToProduce = $quantityInSale - $takenFromStock;
                    
                    $saleProduct->update(['quantity_to_produce' => $quantityToProduce]);
                    
                    // 5.2 Descontar stock del producto terminado
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

                    // 5.3 Descontar stock de los componentes
                    if ($quantityToProduce > 0 && $product->components->isNotEmpty()) {
                        foreach ($product->components as $component) {
                            $requiredQuantity = $component->pivot->quantity * $quantityToProduce;
                            $componentStorage = $component->storages->first();
                            if ($componentStorage) {
                                $componentStorage->decrement('quantity', $requiredQuantity);
                                StockMovement::create([
                                    'product_id' => $component->id,
                                    'storage_id' => $componentStorage->id,
                                    'quantity_change' => $requiredQuantity,
                                    'type' => 'Salida',
                                    'notes' => "Descuento para producir {$quantityToProduce} de {$product->name} (Orden #{$sale->id}) (Actualizado)"
                                ]);
                            }
                        }
                    }
                }
            }
            
            // --- 6. MANEJAR ARCHIVOS ADJUNTOS ---
            if ($request->hasFile('oce_media')) {
                $sale->addMultipleMediaFromRequest(['oce_media'])->each(function ($fileAdder) {
                    $fileAdder->toMediaCollection('oce_media');
                });
            }
            
            DB::commit();

            Log::info("Órden #{$sale->id} (tipo: {$sale->type}) actualizada por el usuario " . auth()->id());
            return redirect()->route('sales.index')->with('success', 'Órden actualizada exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar la órden #{$sale->id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->withInput()->withErrors(['generic_error' => 'Ocurrió un error inesperado.']);
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
        $sales = Sale::with(['user:id,name', 'branch', 'saleProducts.product:id,name,cost'])
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
            ->select('id', 'branch_id', 'quote_id', 'user_id', 'type', 'status', 'total_amount', 'created_at', 'is_high_priority', 'authorized_user_name', 'authorized_at', 'created_at')
            ->get();

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
            'branch.contacts', // Sucursal del cliente y sus contactos
            'user',            // Usuario que creó la venta
            'saleProducts.product.media', // Productos de la venta y su info del catálogo
            'shipments'       // Envíos o parcialidades de la venta
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
                    ->get()
                    ->map(function ($sale) {
                        return [
                            'id' => $sale->id,
                            'name' => ($sale->type === 'venta' ? 'OV-' : 'OS-') . str_pad($sale->id, 4, "0", STR_PAD_LEFT) . ' - ' . ($sale->branch ? $sale->branch->name : 'Sin cliente'),
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
        $sale->load('saleProducts.product.components.storages', 'saleProducts.product.storages');

        foreach ($sale->saleProducts as $saleProduct) {
            $product = $saleProduct->product;
            $totalQuantity = $saleProduct->quantity;
            $quantityToProduce = $saleProduct->quantity_to_produce;

            // 1. Revertir stock de producto terminado
            $takenFromStock = $totalQuantity - $quantityToProduce;
            if ($takenFromStock > 0 && $product->storages->first()) {
                $storage = $product->storages->first();
                $storage->increment('quantity', $takenFromStock);
                
                StockMovement::create([
                    'product_id' => $product->id,
                    'storage_id' => $storage->id,
                    'quantity_change' => $takenFromStock, // Positivo para devolver
                    'type' => 'Entrada',
                    'notes' => "Reversión por Orden #{$sale->id}"
                ]);
            }

            // 2. Revertir stock de componentes
            if ($quantityToProduce > 0 && $product->components->isNotEmpty()) {
                foreach ($product->components as $component) {
                    $requiredQuantity = $component->pivot->quantity * $quantityToProduce;
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

    public function branchSales(Branch $branch)
    {
        // Carga las ventas con las relaciones necesarias para la tabla
        $sales = $branch->sales()
                        ->with(['user:id,name', 'saleProducts.product:id,name,cost'])
                        ->latest() // Ordena por las más recientes
                        ->take(20) // Limita a las 20 más recientes
                        ->get(['id', 'branch_id', 'quote_id', 'user_id', 'type', 'status', 'total_amount', 'created_at', 'authorized_user_name', 'authorized_at']);

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
}
