<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShipmentController extends Controller
{
    public function index()
    {
        // Obtenemos las ventas que tienen al menos un envío registrado.
        // Eager-loading para optimizar consultas y evitar el problema N+1.
        $salesWithShipments = Sale::has('shipments')
            ->with(['shipments', 'branch']) // Carga las relaciones de envíos y sucursal (cliente)
            ->latest() // Ordena por los más recientes
            ->paginate(15); // Pagina los resultados

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
            'contact:id,name', // Información del contacto
            'contact.details', // Información del contacto
            'user:id,name', // Usuario que creó la venta
            'shipments' => function ($query) {
                // Para cada envío, cargar los productos correspondientes
                $query->with([
                    'shipmentProducts.saleProduct.product.media' // Carga el producto de la venta, el producto maestro y sus imágenes
                ])->latest(); // Ordenar los envíos por los más recientes
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

        // Actualiza los campos del envío con la nueva información
        $shipment->update([
            'status' => 'Enviado',
            'sent_at' => now(),
            'notes' => $request->notes,
            'sent_by' => $request->sent_by, // Se guarda el nombre del formulario
        ]);

        // Esta línea se mantiene para tu lógica de negocio de actualizar el estatus de la venta.
        $shipment->sale->updateStatus();

        // Redirige de vuelta a la página anterior con un mensaje de éxito.
        return redirect()->back()->with('success', 'El envío parcial ha sido marcado como "Enviado".');
    }

    public function destroy(Shipment $shipment)
    {
        //
    }
}
