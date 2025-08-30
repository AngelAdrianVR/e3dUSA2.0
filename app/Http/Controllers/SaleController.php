<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
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
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt|max:2048',
        ];

        // --- 2. AÑADIR REGLAS CONDICIONALES PARA 'VENTA' ---
        if ($isSaleType) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
            $rules['contact_id'] = ['required', 'exists:contacts,id'];
            $rules['quote_id'] = ['nullable', 'exists:quotes,id'];
            $rules['order_via'] = ['required', 'string', 'max:255'];
            $rules['freight_option'] = ['required', 'string', 'max:255'];
            $rules['freight_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['shipping_option'] = ['required', 'string'];
            
            $rules['products.*.price'] = ['required', 'numeric', 'min:0'];

            $rules['shipments'] = ['required', 'array', 'min:1'];
            $rules['shipments.*.promise_date'] = ['required', 'date'];
            $rules['shipments.*.shipping_company'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.tracking_guide'] = ['nullable', 'string', 'max:255'];
            $rules['shipments.*.acknowledgement_file'] = ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt|max:2048'];
            $rules['shipments.*.products'] = ['required', 'array'];
            $rules['shipments.*.products.*.product_id'] = ['required', 'exists:products,id'];
            $rules['shipments.*.products.*.quantity'] = ['required', 'integer', 'min:0'];
        } else {
            // Regla para 'stock': el precio no es requerido, pero si se envía debe ser numérico.
            $rules['products.*.price'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

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
                
                // Campos que son nulos para 'stock'
                'branch_id' => $validated['branch_id'] ?? null,
                'contact_id' => $validated['contact_id'] ?? null,
                'quote_id' => $validated['quote_id'] ?? null,
                'order_via' => $validated['order_via'] ?? null,
                'freight_option' => $validated['freight_option'] ?? null,
                'freight_cost' => $validated['freight_cost'] ?? 0,
                
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
                ]);
                $saleProductsMap[$productData['id']] = $saleProduct->id;
            }

            // --- 5. GUARDAR ENVÍOS (SOLO SI ES VENTA) ---
            if ($isSaleType && !empty($validated['shipments'])) {
                foreach ($validated['shipments'] as $index => $shipmentData) {
                    $shipment = $sale->shipments()->create([
                        'status' => 'Pendiente',
                        'promise_date' => $shipmentData['promise_date'],
                        'shipping_company' => $shipmentData['shipping_company'] ?? null,
                        'tracking_guide' => $shipmentData['tracking_guide'] ?? null,
                    ]);

                    if ($request->hasFile("shipments.{$index}.acknowledgement_file")) {
                        $shipment->addMedia($request->file("shipments.{$index}.acknowledgement_file"))->toMediaCollection('acuse_files');
                    }

                    foreach ($shipmentData['products'] as $shipmentProductData) {
                        if ($shipmentProductData['quantity'] > 0) {
                            $shipment->shipmentProducts()->create([
                                'sale_product_id' => $saleProductsMap[$shipmentProductData['product_id']],
                                'quantity' => $shipmentProductData['quantity'],
                            ]);
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
            'user',
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
            'oce_media.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xml,txt|max:2048',
        ];

        // --- 2. AÑADIR REGLAS CONDICIONALES PARA 'VENTA' ---
        if ($isSaleType) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
            $rules['contact_id'] = ['required', 'exists:contacts,id'];
            $rules['quote_id'] = ['nullable', 'exists:quotes,id'];
            $rules['order_via'] = ['required', 'string', 'max:255'];
            $rules['freight_option'] = ['required', 'string', 'max:255'];
            $rules['freight_cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['shipping_option'] = ['required', 'string'];
            $rules['products.*.price'] = ['required', 'numeric', 'min:0'];
            $rules['shipments'] = ['nullable', 'array']; // Cambiado a nullable para flexibilidad
            $rules['shipments.*.promise_date'] = ['required', 'date'];
            // ... (resto de reglas de shipments)
        } else {
            $rules['products.*.price'] = ['nullable', 'numeric', 'min:0'];
        }

        $validated = $request->validate($rules);

        DB::beginTransaction();

        try {
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
                'total_amount' => $isSaleType ? array_reduce($validated['products'], function ($carry, $product) {
                    return $carry + ($product['quantity'] * ($product['price'] ?? 0));
                }, 0) : 0,
            ]);

            // --- 4. SINCRONIZAR PRODUCTOS ---
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
                    ]
                );
            }

            // --- 5. SINCRONIZAR ENVÍOS (SOLO SI ES VENTA) ---
            if ($isSaleType) {
                // Eliminar envíos anteriores para evitar conflictos
                $sale->shipments()->each(function ($shipment) {
                    $shipment->shipmentProducts()->delete();
                    $shipment->delete();
                });

                $saleProductsMap = $sale->saleProducts()->pluck('id', 'product_id');

                if (!empty($validated['shipments'])) {
                    foreach ($validated['shipments'] as $index => $shipmentData) {
                        $shipment = $sale->shipments()->create([
                            'status' => 'Pendiente',
                            'promise_date' => $shipmentData['promise_date'],
                            'shipping_company' => $shipmentData['shipping_company'] ?? null,
                            'tracking_guide' => $shipmentData['tracking_guide'] ?? null,
                        ]);
                        // Lógica para archivos de acuse (si aplica en edición)
                        if ($request->hasFile("shipments.{$index}.acknowledgement_file")) {
                            $shipment->clearMediaCollection('acuse_files');
                            $shipment
                                ->addMedia($request->file("shipments.{$index}.acknowledgement_file"))
                                ->toMediaCollection('acuse_files');
                        }

                        // AÑADIMOS ESTA CONDICIÓN PARA EVITAR EL ERROR SI NO EXISTE UNA CLAVE DE PRODUCTO
                        if (isset($shipmentData['products']) && is_array($shipmentData['products'])) {
                            foreach ($shipmentData['products'] as $shipmentProductData) {
                                if ($shipmentProductData['quantity'] > 0 && isset($saleProductsMap[$shipmentProductData['product_id']])) {
                                    $shipment->shipmentProducts()->create([
                                        'sale_product_id' => $saleProductsMap[$shipmentProductData['product_id']],
                                        'quantity' => $shipmentProductData['quantity'],
                                    ]);
                                }
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

    public function destroy(Sale $sale)
    {
        $sale->delete();
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Sale::find($id);
            $bonus?->delete();
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
}
