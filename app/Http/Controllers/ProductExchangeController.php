<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductExchange;
use App\Models\StockMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProductExchangeController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación
        $validated = $request->validate([
            'sale_id'             => 'required|exists:sales,id',
            'returned_product_id' => 'required|exists:products,id',
            'returned_quantity'   => 'required|integer|min:1',
            'new_product_id'      => 'required|exists:products,id',
            'new_quantity'        => 'required|integer|min:1',
            'price_difference'    => 'nullable|numeric',
            'reason'              => 'required|string|max:500',
            'evidence_images.*'   => 'image|max:2048', 
        ]);

        try {
            return DB::transaction(function () use ($validated, $request) {
                
                // --- A. Calcular Diferencia de Precio ---
                $returnedProduct = Product::find($validated['returned_product_id']);
                $newProduct = Product::find($validated['new_product_id']);
                
                // Si es positivo, el cliente paga más.
                // $priceDiff = ($newProduct->base_price * $validated['new_quantity']) - 
                //              ($returnedProduct->base_price * $validated['returned_quantity']);

                // --- B. Crear el Registro de Intercambio ---
                $exchange = ProductExchange::create([
                    'sale_id'             => $validated['sale_id'],
                    'user_id'             => Auth::id(),
                    'returned_product_id' => $validated['returned_product_id'],
                    'returned_quantity'   => $validated['returned_quantity'],
                    'new_product_id'      => $validated['new_product_id'],
                    'new_quantity'        => $validated['new_quantity'],
                    'price_difference'    => $validated['price_difference'],
                    'reason'              => $validated['reason'],
                ]);

                // --- C. Guardar Imágenes (Spatie Media Library) ---
                if ($request->hasFile('evidence_images')) {
                    foreach ($request->file('evidence_images') as $image) {
                        $exchange->addMedia($image)->toMediaCollection('evidence');
                    }
                }

                // --- D. Lógica de Inventario (CORREGIDA) ---
                
                // 1. ENTRADA (El producto que regresa al stock)
                $returnedStorage = $returnedProduct->storages()->firstOrCreate([], ['quantity' => 0]);
                $returnedStorage->increment('quantity', $exchange->returned_quantity);

                StockMovement::create([
                    'product_id'      => $exchange->returned_product_id,
                    'storage_id'      => $returnedStorage->id,
                    'quantity_change' => $exchange->returned_quantity,
                    'type'            => 'Entrada', 
                    'notes'           => "Devolución por cambio Folio #{$exchange->id}",
                    'user_id'         => Auth::id(),
                ]);

                // 2. SALIDA (El producto nuevo que se entrega)
                $newStorage = $newProduct->storages()->firstOrCreate([], ['quantity' => 0]);
                $newStorage->decrement('quantity', $exchange->new_quantity);

                StockMovement::create([
                    'product_id'      => $exchange->new_product_id,
                    'storage_id'      => $newStorage->id,
                    'quantity_change' => -($exchange->new_quantity),
                    'type'            => 'Salida',
                    'notes'           => "Salida por cambio Folio #{$exchange->id}",
                    'user_id'         => Auth::id(),
                ]);

            });

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al procesar el cambio: ' . $e->getMessage()], 500);
        }
    }
    
    public function show($id)
    {
        $exchange = ProductExchange::with(['sale', 'returnedProduct', 'newProduct', 'media'])->findOrFail($id);
        return response()->json($exchange);
    }

    public function print($id)
    {
        // Buscamos el intercambio por ID y cargamos toda la información necesaria
        $exchange = ProductExchange::with([
            'sale',                 // Para el folio de la venta (OV-xxxx)
            'sale.branch',          // Datos del Cliente
            'sale.contact',         // Contacto del cliente
            'returnedProduct.media',      // Producto que entra
            'newProduct.media',           // Producto que sale
            'user',                 // Quien autorizó/realizó el cambio
            'media'                 // Imágenes de evidencia
        ])->findOrFail($id);

        return Inertia::render('Sale/PrintProductExchange', [
            'exchange' => $exchange
        ]);
    }
}
