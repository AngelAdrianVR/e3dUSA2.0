<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\BranchPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BranchPriceHistoryController extends Controller
{
    public function store(Request $request, Branch $branch, Product $product)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:MXN,USD',
            'valid_from' => 'required|date',
        ]);

        // --- Validación de la regla de negocio del 4% en el backend ---
        $lastPriceRecord = BranchPriceHistory::where('branch_id', $branch->id)
            ->where('product_id', $product->id)
            ->whereNull('valid_to') // Busca el precio vigente
            ->latest('valid_from')
            ->first();

        // El precio de referencia es el último precio especial o el precio base del producto
        $basePrice = $lastPriceRecord ? $lastPriceRecord->price : $product->base_price;
        $minAllowedPrice = $basePrice * 0.96;

        if ($validated['amount'] < $minAllowedPrice) {
            // Lanza una excepción de validación que será capturada por el frontend
            throw ValidationException::withMessages([
                'amount' => 'El precio no puede tener un descuento mayor al 4%. El mínimo permitido es $' . number_format($minAllowedPrice, 2),
            ]);
        }

        // --- Gestión del historial de precios ---

        // 1. "Cierra" el registro de precio anterior si existe uno vigente
        if ($lastPriceRecord) {
            // La vigencia del precio anterior termina un día antes de que empiece el nuevo
            $lastPriceRecord->valid_to = Carbon::parse($validated['valid_from'])->subDay();
            $lastPriceRecord->save();
        }

        // 2. Crea el nuevo registro de precio
        BranchPriceHistory::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'price' => $validated['amount'],
            'currency' => $validated['currency'],
            'valid_from' => Carbon::parse($validated['valid_from']),
            'valid_to' => null, // null significa que está vigente indefinidamente
        ]);

        return response()->json(['message' => 'Precio actualizado correctamente.']);
    }

     /**
     * Finaliza la vigencia de un registro de precio especial estableciendo la fecha actual.
     *
     * @param  \App\Models\BranchPriceHistory  $priceHistory
     * @return \Illuminate\Http\JsonResponse
     */
    public function close(BranchPriceHistory $priceHistory)
    {
        // Asigna la fecha y hora actual para marcar el fin de la vigencia
        $priceHistory->valid_to = Carbon::now();
        $priceHistory->save();

        return response()->json(['message' => 'El precio especial ha sido finalizado.']);
    }
}
