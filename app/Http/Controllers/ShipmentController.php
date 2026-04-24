<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Shipment;
use App\Models\SaleProduct;
use App\Models\ShipmentProduct;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon; // Agregado para el manejo de fechas

class ShipmentController extends Controller
{
    public function index(Request $request)
    {
        // Obtenemos los filtros de la solicitud, con valores por defecto
        $statusFilter = $request->input('status', 'Todos');
        $partialsFilter = $request->input('partials', 'Todas');

        // Iniciamos la consulta base
        $query = Sale::has('shipments');

        // Aplicar filtro por Estatus del envío
        if ($statusFilter !== 'Todos') {
            $query->whereHas('shipments', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            });
        }

        // Aplicar filtro por Parcialidades (Cantidad de envíos)
        if ($partialsFilter === 'Una parcialidad') {
            $query->has('shipments', '=', 1);
        } elseif ($partialsFilter === 'Varias parcialidades') {
            $query->has('shipments', '>', 1);
        }

        // Eager-loading para optimizar consultas y evitar el problema N+1.
        $salesWithShipments = $query
            ->with([
                'shipments', 
                'branch:id,name',
            ]) // Carga las relaciones de envíos y sucursal (cliente)
            ->select(['id', 'branch_id', 'status', 'promise_date', 'freight_cost'])
            ->latest() // Ordena por los más recientes
            ->paginate(20) // Pagina los resultados
            ->withQueryString(); // Mantiene los parámetros de la URL en la paginación

        // Renderiza la vista de Inertia, pasando los datos y los filtros actuales
        return Inertia::render('Shipment/Index', [
            'sales' => $salesWithShipments,
            'filters' => [
                'status' => $statusFilter,
                'partials' => $partialsFilter,
            ]
        ]);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        // Validación de la solicitud para la nueva parcialidad
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'promise_date' => 'required|date',
            'shipping_company' => 'nullable|string|max:255',
            'tracking_guide' => 'nullable|string|max:255',
            'products' => 'required|array|min:1',
            'products.*.sale_product_id' => 'required|exists:sale_products,id',
            'products.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $sale = Sale::findOrFail($validated['sale_id']);

                // 1. Crear el nuevo registro del envío (Parcialidad)
                $newShipment = $sale->shipments()->create([
                    'status' => 'Pendiente',
                    'promise_date' => $validated['promise_date'],
                    'shipping_company' => $validated['shipping_company'] ?? null,
                    'tracking_guide' => $validated['tracking_guide'] ?? null,
                ]);

                // 2. Procesar y reasignar cada producto
                foreach ($validated['products'] as $prod) {
                    $saleProductId = $prod['sale_product_id'];
                    $qtyNeeded = $prod['quantity'];

                    $saleProduct = SaleProduct::findOrFail($saleProductId);
                    $totalInSale = $saleProduct->quantity;

                    // Cuánto de este producto está asignado en TODOS los envíos de la orden actualmente
                    $totalAssigned = ShipmentProduct::whereHas('shipment', function ($q) use ($sale) {
                        $q->where('sale_id', $sale->id);
                    })->where('sale_product_id', $saleProductId)->sum('quantity');

                    // Calculamos si hay productos "libres" sin asignar a ninguna parcialidad
                    $unassigned = max(0, $totalInSale - $totalAssigned);
                    $shortage = $qtyNeeded - $unassigned;

                    // Si solicitamos más de lo libre, robamos de los envíos PENDIENTES o AUTORIZADOS
                    if ($shortage > 0) {
                        $pendingShipmentProducts = ShipmentProduct::whereHas('shipment', function ($q) use ($sale) {
                            $q->where('sale_id', $sale->id)->whereIn('status', ['Pendiente', 'Autorizado']);
                        })->where('sale_product_id', $saleProductId)->orderBy('id', 'asc')->get();

                        foreach ($pendingShipmentProducts as $psp) {
                            if ($shortage <= 0) break;

                            $deduct = min($psp->quantity, $shortage);
                            $psp->quantity -= $deduct;
                            
                            if ($psp->quantity <= 0) {
                                $psp->delete(); // Si le quitamos todo, borramos el registro pivote
                            } else {
                                $psp->save(); // Si le quitamos una parte, guardamos la resta
                            }
                            
                            $shortage -= $deduct;
                        }

                        // Medida de seguridad en caso de desbordamiento
                        if ($shortage > 0) {
                            throw new \Exception("No hay suficiente cantidad en envíos pendientes para reasignar el producto a esta parcialidad.");
                        }
                    }

                    // 3. Asignar los productos definitivos a la nueva parcialidad
                    $newShipment->shipmentProducts()->create([
                        'sale_product_id' => $saleProductId,
                        'quantity' => $qtyNeeded,
                    ]);
                }

                // 4. Limpieza: si algún envío pendiente anterior se quedó sin NINGÚN producto, lo eliminamos.
                $emptyShipments = $sale->shipments()->whereIn('status', ['Pendiente', 'Autorizado'])->doesntHave('shipmentProducts')->get();
                foreach ($emptyShipments as $es) {
                    $es->delete();
                }

                // Actualizar estatus de la venta
                $sale->updateStatus();
            });

            return back()->with('success', 'Nueva parcialidad agregada y productos redistribuidos exitosamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function show(Sale $sale)
    {
        // Cargar la venta con todas las relaciones anidadas necesarias para la vista.
        // Esto optimiza las consultas a la base de datos.
        $sale->load([
            'branch:id,name,rfc,address,post_code,status', // Información del cliente
            'contact:id,name',
            'contact.details', // Información del contacto
            'user:id,name', // Usuario que creó la venta
            'shipments' => function ($query) {
                // Para cada envío, cargar los productos correspondientes
                $query->with([
                    'shipmentProducts.saleProduct.product:id,name,code,measure_unit',
                    'shipmentProducts.saleProduct.product.media', // Carga el producto de la venta, el producto maestro y sus imágenes
                    'shipmentProducts.saleProduct.product.storages', // Carga el stock
                    'media', // <--- AGREGADO: Cargar evidencias del envío
                ])->oldest(); // Ordenar los envíos por los más recientes
            }
        ]);

        return Inertia::render('Shipment/Show', [
            'sale' => $sale,
        ]);
    }

    public function edit(Shipment $shipment)
    {
        //
    }

    public function update(Request $request, Shipment $shipment)
    {
        // Validación de los datos que vienen del modal incluyendo fecha y productos a despachar.
        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'sent_by' => 'required|string|max:255',
            'sent_at' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:shipment_products,id',
            'products.*.quantity' => 'required|integer|min:0',
            'products.*.reason' => 'nullable|string|max:500', // Nueva validación para la razón
        ]);

        // Validación personalizada antes de la transacción para asegurar que haya razón si mandan menos.
        foreach ($request->products as $prod) {
            $sp = ShipmentProduct::with('saleProduct.product')->find($prod['id']);
            if ($sp && $prod['quantity'] > 0 && $prod['quantity'] < $sp->quantity) {
                if (empty($prod['reason'])) {
                    $productName = $sp->saleProduct->product->name ?? 'Desconocido';
                    return back()->withErrors(['products' => "Es obligatorio especificar una razón al enviar menos piezas de las programadas para el producto: {$productName}."]);
                }
            }
        }

        // Se envuelve la lógica en una transacción para asegurar la integridad de los datos.
        DB::transaction(function () use ($request, $shipment) {
            
            // 1. Actualiza los campos del envío con la nueva información
            $shipment->update([
                'status' => 'Enviado',
                'sent_at' => Carbon::parse($request->sent_at),
                'notes' => $request->notes,
                'sent_by' => $request->sent_by,
            ]);

            // Mapear los productos recibidos para acceder a la nueva cantidad
            $submittedProducts = collect($request->products)->keyBy('id');

            // 2. Lógica para descontar stock y ajustar cantidades parciales
            $shipment->load('shipmentProducts.saleProduct.product.storages');

            foreach ($shipment->shipmentProducts as $shipmentProduct) {
                if (!$submittedProducts->has($shipmentProduct->id)) {
                    continue; 
                }

                $submittedQty = $submittedProducts[$shipmentProduct->id]['quantity'];
                $reason = $submittedProducts[$shipmentProduct->id]['reason'] ?? null;

                // Evitar que despachen más de lo originalmente programado en la parcialidad
                $newQty = min($submittedQty, $shipmentProduct->quantity);

                // Si el usuario puso 0, se elimina el registro (se regresa a inventario "no enviado").
                if ($newQty <= 0) {
                    $shipmentProduct->delete();
                    continue; 
                } else if ($newQty < $shipmentProduct->quantity) {
                    // Si mandaron menos, actualizamos el registro guardando la cantidad original y la razón
                    $shipmentProduct->update([
                        'quantity' => $newQty,
                        'original_quantity' => $shipmentProduct->quantity,
                        'less_sent_reason' => $reason
                    ]);
                }

                $product = $shipmentProduct->saleProduct->product;

                if ($product) {
                    $saleProduct = $shipmentProduct->saleProduct;
                    
                    // Este es el TOTAL que la orden debía producir.
                    $maxToDeduct = $saleProduct->quantity_to_produce; 

                    // Calcular cuánto de este producto EXACTO ya se ha descontado en envíos anteriores.
                    $previouslyShipped = \App\Models\ShipmentProduct::where('sale_product_id', $saleProduct->id)
                        ->whereHas('shipment', function($q) use ($shipment) {
                            $q->where('status', 'Enviado')
                              ->where('id', '!=', $shipment->id); 
                        })->sum('quantity');

                    // Cuánto nos queda permitido descontar del inventario general para esta orden
                    $leftToDeduct = max(0, $maxToDeduct - $previouslyShipped);
                    
                    // Solo descontamos lo que trae la caja física (nueva cantidad) 
                    // o lo que nos queda por descontar, lo que sea menor.
                    $quantityToDecrement = min($newQty, $leftToDeduct);

                    if ($quantityToDecrement > 0) {
                        $storage = $product->storages()->first();

                        if ($storage) {
                            $storage->decrement('quantity', $quantityToDecrement);

                            StockMovement::create([
                                'product_id' => $product->id,
                                'storage_id' => $storage->id,
                                'quantity_change' => $quantityToDecrement,
                                'type' => 'Salida',
                                'notes' => 'Salida por envío (total/parcial) de OV-' . $shipment->sale_id
                            ]);
                        }
                    }
                }
            }

            // 3. Limpieza: si la parcialidad se quedó sin NINGÚN producto, la eliminamos.
            if ($shipment->shipmentProducts()->count() === 0) {
                $shipment->delete();
            }
            
            // 4. Actualiza el estatus general de la venta.
            $shipment->sale->updateStatus();
        });

        // Redirige de vuelta a la página anterior con un mensaje de éxito.
        return redirect()->back()->with('success', 'El envío ha sido marcado como "Enviado" y el stock ha sido calculado y actualizado correctamente.');
    }

    public function destroy(Shipment $shipment)
    {
        if ($shipment->status === 'Enviado') {
            return back()->withErrors(['message' => 'No puedes eliminar un envío que ya fue entregado a la paquetería.']);
        }

        $sale = $shipment->sale;

        DB::transaction(function () use ($shipment) {
            $shipment->delete(); // Elimina el envío y en cascada a sus shipment_products
        });

        // Actualizar estatus de la venta para saber en qué etapa se quedó
        $sale->updateStatus();

        return back()->with('success', 'Parcialidad eliminada correctamente y productos regresados a la orden general.');
    }

    /**
     * Actualiza la cantidad individual de un producto dentro de una parcialidad
     * Ruta: PUT /shipments/products/{shipmentProduct}/quantity
     */
    public function updateProductQuantity(Request $request, $shipmentProductId)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request, $shipmentProductId) {
                // Cargamos la relación completa para tener el contexto de la orden
                $shipmentProduct = ShipmentProduct::with(['shipment.sale', 'saleProduct'])->findOrFail($shipmentProductId);
                
                if ($shipmentProduct->shipment->status === 'Enviado') {
                    throw new \Exception('No se puede modificar la cantidad de un producto en un envío que ya fue entregado.');
                }

                $saleProduct = $shipmentProduct->saleProduct;
                $sale = $shipmentProduct->shipment->sale;

                // Calcular cuánto está asignado de este producto en OTRAS parcialidades
                $totalAssignedElsewhere = ShipmentProduct::whereHas('shipment', function ($q) use ($sale) {
                    $q->where('sale_id', $sale->id);
                })->where('sale_product_id', $saleProduct->id)
                  ->where('id', '!=', $shipmentProduct->id)
                  ->sum('quantity');

                // Lo máximo que puede tener esta parcialidad es (Total de la orden) - (Asignado en otras parcialidades)
                $maxAllowed = $saleProduct->quantity - $totalAssignedElsewhere;

                if ($request->quantity > $maxAllowed) {
                    throw new \Exception("La cantidad máxima que puedes asignar a esta parcialidad es de {$maxAllowed} pzas (el resto ya está programado en otros envíos).");
                }

                // Actualizamos la cantidad en la base de datos
                $shipmentProduct->update([
                    'quantity' => $request->quantity
                ]);
            });

            return back()->with('success', 'Cantidad de la parcialidad actualizada correctamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');
        $statusFilter = $request->input('status', 'Todos');
        $partialsFilter = $request->input('partials', 'Todas');

        $salesQuery = Sale::has('shipments');

        // Aplicar filtro por Estatus
        if ($statusFilter !== 'Todos') {
            $salesQuery->whereHas('shipments', function($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            });
        }

        // Aplicar filtro por Parcialidades
        if ($partialsFilter === 'Una parcialidad') {
            $salesQuery->has('shipments', '=', 1);
        } elseif ($partialsFilter === 'Varias parcialidades') {
            $salesQuery->has('shipments', '>', 1);
        }

        // Búsqueda por texto
        if (!empty($query)) {
            $salesQuery->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhereHas('shipments', function ($parentQuery) use ($query) {
                    $parentQuery->where('status', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            });
        }

        // Realiza la búsqueda
        $sales = $salesQuery
            ->with(['shipments', 'branch:id,name'])
            ->latest()
            ->select(['id', 'branch_id', 'status', 'promise_date', 'freight_cost'])
            ->get();

        return response()->json(['items' => $sales], 200);
    }

    /**
     * Actualiza la guía de rastreo y paquetería de un envío.
     * Ruta sugerida: PUT /shipments/{shipment}/tracking
     */
    public function updateTracking(Request $request, Shipment $shipment)
    {
        $validated = $request->validate([
            'shipping_company' => 'required|string|max:255',
            'tracking_guide' => 'required|string|max:255',
        ]);

        $shipment->update($validated);

        return back()->with('success', 'Información de rastreo actualizada correctamente.');
    }

    /**
     * Sube archivos de evidencia al envío.
     * Ruta sugerida: POST /shipments/{shipment}/evidence
     */
    public function storeEvidence(Request $request, Shipment $shipment)
    {
        $request->validate([
            'evidence_files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,webp|max:5120', // Máx 5MB por archivo
        ]);

        if ($request->hasFile('evidence_files')) {
            foreach ($request->file('evidence_files') as $file) {
                $shipment->addMedia($file)->toMediaCollection('shipment-evidence');
            }
        }

        return back()->with('success', 'Evidencias subidas correctamente.');
    }
}