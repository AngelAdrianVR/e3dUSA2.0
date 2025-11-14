<?php

namespace App\Http\Controllers;

use App\Models\FavoredStockRequest;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FavoredStockRequestController extends Controller
{
    /**
     * Marcar una solicitud de stock a favor como "Recibida"
     * y ejecutar los movimientos de inventario.
     */
    public function receive(Request $request, FavoredStockRequest $favoredStockRequest)
    {
        // Validar que no esté ya recibida
        if ($favoredStockRequest->status === 'Recibido') {
            return back()->withErrors(['message' => 'Esta solicitud ya ha sido marcada como recibida.']);
        }

        try {
            DB::transaction(function () use ($favoredStockRequest) {
                // 1. Actualizar la solicitud
                $favoredStockRequest->update([
                    'status' => 'Recibido',
                    'received_at' => now(),
                ]);

                // 2. Cargar las relaciones necesarias
                $favoredStockRequest->load('favoredProduct.product.storages', 'favoredProduct.supplier');

                $product = $favoredStockRequest->favoredProduct->product;
                $supplierName = $favoredStockRequest->favoredProduct->supplier->name ?? 'N/A';
                $quantityToReceive = $favoredStockRequest->quantity_requested;

                if (!$product) {
                    throw new \Exception('El producto de la solicitud no fue encontrado.');
                }

                // 3. Agregar la cantidad al stock del producto principal
                $storage = $product->storages()->firstOrCreate([], ['quantity' => 0]);
                $storage->increment('quantity', $quantityToReceive);

                // 4. Crear el movimiento de stock (la entrada real)
                StockMovement::create([
                    'product_id' => $product->id,
                    'storage_id' => $storage->id,
                    'quantity_change' => $quantityToReceive,
                    'type' => 'Entrada',
                    'notes' => 'Entrada por recepción de stock a favor (Proveedor: ' . $supplierName . ')',
                    'shipping_method' => $favoredStockRequest->shipping_method,
                    'user_id' => Auth::id(), // Usuario que *recibe*
                ]);
            });

            // Redireccionar de vuelta con éxito
            // Usar 'back()' es mejor para Inertia cuando es una acción
            return back()->with('success', 'Stock marcado como recibido y agregado al inventario.');

        } catch (\Exception $e) {
            // Log::error('Error al recibir stock a favor: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Error al procesar la recepción: ' . $e->getMessage()]);
        }
    }
}