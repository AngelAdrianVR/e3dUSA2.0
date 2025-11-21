<?php

namespace App\Http\Controllers;

use App\Models\FavoredProduct;
use App\Models\FavoredStockRequest;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FavoredProductController extends Controller
{
    /**
     * Display a listing of the resource for a specific supplier.
     */
    public function index(Supplier $supplier)
    {
        $favoredProducts = $supplier->favoredProducts()
            ->with(['product.media', 'product:id,name,code,measure_unit'])
            ->get();
        
        return response()->json([
            'favoredProducts' => $favoredProducts,
            'supplierName' => $supplier->name,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function discount(Request $request, FavoredProduct $favoredProduct)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.01|max:' . $favoredProduct->quantity,
            'shipping_method' => 'required|string|in:plane,ship,factory',
        ]);

        try {
            $favoredProduct = DB::transaction(function () use ($request, $favoredProduct) {
                $quantityToDiscount = $request->input('quantity');
                $shippingMethod = $request->input('shipping_method');
                
                // 1. Snapshot de la cantidad ANTES
                $quantityBefore = $favoredProduct->quantity;

                // 2. Descontar la cantidad del producto a favor
                $favoredProduct->decrement('quantity', $quantityToDiscount);

                // 3. Snapshot de la cantidad DESPUÉS
                $quantityAfter = $favoredProduct->quantity;

                // 4. Crear el registro de la solicitud de stock
                FavoredStockRequest::create([
                    'favored_product_id' => $favoredProduct->id,
                    'user_id' => Auth::id(),
                    'quantity_requested' => $quantityToDiscount,
                    'shipping_method' => $shippingMethod,
                    'quantity_before_request' => $quantityBefore,
                    'quantity_after_request' => $quantityAfter,
                    'status' => 'Solicitado',
                ]);
                
                // 5. Devolver el producto a favor actualizado
                return $favoredProduct;
            });

            // Devolver el producto actualizado
            // Cargar product.media para consistencia con el frontend
            $favoredProduct->load('product.media');
            
            // Si la cantidad es 0, el frontend lo manejará (lo quitará de la lista)
            return response()->json($favoredProduct);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al solicitar el stock: ' . $e->getMessage()], 500);
        }
    }
}
