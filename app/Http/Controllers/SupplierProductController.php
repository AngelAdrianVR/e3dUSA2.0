<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierProductController extends Controller
{
    public function store(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                // Asegurarse de que el producto no esté ya asignado a este proveedor
                Rule::unique('product_supplier')->where(function ($query) use ($supplier) {
                    return $query->where('supplier_id', $supplier->id);
                }),
            ],
            'last_price' => 'required|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
        ]);

        // Actualizar costo del producto
        $product = Product::findOrFail($validated['product_id']);
        $product->update([
            'cost' => $validated['last_price'],
        ]);

        $supplier->products()->attach($validated['product_id'], [
            'last_price' => $validated['last_price'],
            'min_quantity' => $validated['min_quantity'],
        ]);

        return back()->with('success', 'Producto asignado al proveedor.');
    }

    public function update(Request $request, Supplier $supplier, Product $product)
    {
        $validated = $request->validate([
            'last_price' => 'required|numeric|min:0',
            'min_quantity' => 'nullable|numeric|min:0',
        ]);

        $product->update([
            'cost' => $request->last_price    
        ]);
        
        $supplier->products()->updateExistingPivot($product->id, $validated);

        return back()->with('success', 'Producto actualizado para este proveedor.');
    }

    public function destroy(Supplier $supplier, Product $product)
    {
        $supplier->products()->detach($product->id);

        return back()->with('success', 'Producto desvinculado del proveedor.');
    }
}
