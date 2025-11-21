<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Shipment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShipmentController extends Controller
{
    public function index()
    {
        // Obtenemos las ventas que tienen al menos un envío registrado.
        // Eager-loading para optimizar consultas y evitar el problema N+1.
        $salesWithShipments = Sale::has('shipments')
            ->with([
                'shipments', 
                'branch:id,name',
            ]) // Carga las relaciones de envíos y sucursal (cliente)
            ->select(['id', 'branch_id', 'status', 'promise_date', 'freight_cost'])
            ->latest() // Ordena por los más recientes
            ->paginate(20); // Pagina los resultados

            // return $salesWithShipments;
        // Renderiza la vista de Inertia, pasando los datos de las ventas.
        return Inertia::render('Shipment/Index', [
            'sales' => $salesWithShipments,
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
                    'shipmentProducts.saleProduct.product.storages' // Carga el stock
                ])->oldest(); // Ordenar los envíos por los más recientes
            }
        ]);

        // return $sale;
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
        // Validación de los datos que vienen del modal.
        $request->validate([
            'notes' => 'nullable|string|max:1000', // Las notas son opcionales
            'sent_by' => 'required|string|max:255', // El nombre de quien envía es requerido
        ]);

        // --- INICIO DE LA LÓGICA MODIFICADA ---
        // Se envuelve la lógica en una transacción para asegurar la integridad de los datos.
        // Si algo falla al descontar el stock, no se marcará como enviado.
        DB::transaction(function () use ($request, $shipment) {
            
            // 1. Actualiza los campos del envío con la nueva información
            $shipment->update([
                'status' => 'Enviado',
                'sent_at' => now(),
                'notes' => $request->notes,
                'sent_by' => $request->sent_by,
            ]);

            // 2. Lógica para descontar stock
            // Cargar las relaciones necesarias para acceder a los productos y su stock.
            $shipment->load('shipmentProducts.saleProduct.product.storages');

            foreach ($shipment->shipmentProducts as $shipmentProduct) {
                $product = $shipmentProduct->saleProduct->product;

                // descuenta unicamente la cantidad producida, ya que el resto tomado del stock del producto terminado ya fue descontado
                $quantityToDecrement = $shipmentProduct->saleProduct->quantity_to_produce; 

                // Asegurarse de que el producto existe y la cantidad a descontar es mayor a cero.
                if ($product && $quantityToDecrement > 0) {
                    // Se asume que el descuento se hace del primer almacén (storage) asociado al producto.
                    // Si tienes una lógica de múltiples almacenes, deberás ajustarla aquí.
                    $storage = $product->storages()->first();

                    if ($storage) {
                        // Utiliza decrement() para una operación atómica y segura en la base de datos.
                        $storage->decrement('quantity', $quantityToDecrement);

                        StockMovement::create([
                            'product_id' => $product->id,
                            'storage_id' => $storage->id,
                            'quantity_change' => $quantityToDecrement,
                            'type' => 'Salida',
                            'notes' => 'Salida por envío de OV-' . $shipment->sale_id
                        ]);
                    }
                }
            }
            
            // 3. Actualiza el estatus general de la venta.
            $shipment->sale->updateStatus();
        });
        // --- FIN DE LA LÓGICA MODIFICADA ---

        // Redirige de vuelta a la página anterior con un mensaje de éxito.
        return redirect()->back()->with('success', 'El envío parcial ha sido marcado como "Enviado" y el stock ha sido actualizado.');
    }

    public function destroy(Shipment $shipment)
    {
        //
    }

    public function getMatches(Request $request)
    {
        $query = $request->input('query');

        // Realiza la búsqueda
        $sales = Sale::has('shipments')
            ->with(['shipments', 'branch:id,name'])
            ->latest()
            ->where(function ($q) use ($query) {
                $q->where('id', 'like', "%{$query}%")
                ->orWhereHas('shipments', function ($parentQuery) use ($query) {
                    $parentQuery->where('status', 'like', "%{$query}%");
                })
                ->orWhereHas('branch', function ($userquery) use ($query) {
                    $userquery->where('name', 'like', "%{$query}%");
                });
            })
            ->select(['id', 'branch_id', 'status', 'promise_date', 'freight_cost'])
            ->get();

        return response()->json(['items' => $sales], 200);
    }
}
