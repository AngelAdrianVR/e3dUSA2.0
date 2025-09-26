<?php

namespace App\Http\Controllers;

use App\Models\FavoredProduct;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
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
        ]);

        try {
            DB::transaction(function () use ($request, $favoredProduct) {
                $quantityToDiscount = $request->input('quantity');
                
                // Cargar la relación con el producto principal
                $product = $favoredProduct->product;

                if (!$product) {
                    throw new \Exception('El producto a favor no está asociado a ningún producto del inventario.');
                }

                // 1. Descontar la cantidad del producto a favor
                $favoredProduct->decrement('quantity', $quantityToDiscount);

                // 2. Agregar la cantidad al stock del producto principal
                $storage = $product->storages()->firstOrCreate([], ['quantity' => 0]);
                $storage->increment('quantity', $quantityToDiscount);

                // 3. Crear el movimiento de stock
                StockMovement::create([
                    'product_id' => $product->id,
                    'storage_id' => $storage->id,
                    'quantity_change' => $quantityToDiscount,
                    'type' => 'Entrada',
                    'notes' => 'Entrada por descuento de stock a favor'
                ]);
            });

            // Devolver el producto actualizado
            $favoredProduct->refresh()->load('product');
            return response()->json($favoredProduct);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al descontar la cantidad: ' . $e->getMessage()], 500);
        }
    }
}
