<?php

namespace App\Http\Controllers;

use App\Models\FavoredProduct;
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
            DB::beginTransaction();

            $favoredProduct->decrement('quantity', $request->quantity);

            // Opcional: Registrar el descuento en un log o historial
            // History::create([...]);

            DB::commit();

            // Devolver el producto actualizado
            $favoredProduct->load('product'); // Recargar la relación del producto
            return response()->json($favoredProduct);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al descontar la cantidad.'], 500);
        }
    }
}
