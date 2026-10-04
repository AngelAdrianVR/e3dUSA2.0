<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\BranchPriceHistory;
use App\Services\BranchGroupService;
use App\Services\BranchVolumePriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class BranchPriceHistoryController extends Controller
{
    public function __construct(
        private BranchGroupService $branchGroups,
        private BranchVolumePriceService $volumePrices
    ) {
    }

    public function store(Request $request, Branch $branch, Product $product)
    {
        $validated = $request->validate(array_merge([
            'amount' => 'nullable|numeric|min:0.01',
            'currency' => 'required_with:amount|string|in:MXN,USD',
            'valid_from' => 'required_with:amount|date',
            'sync_volume_prices' => 'boolean',
        ], BranchVolumePriceService::rules()));

        $syncVolumePrices = $request->boolean('sync_volume_prices');
        $volumePrices = $validated['volume_prices'] ?? [];

        // Se puede actualizar el precio, los precios por volumen, o ambos.
        if (empty($validated['amount']) && !$syncVolumePrices) {
            throw ValidationException::withMessages([
                'amount' => 'Ingresa un precio nuevo o modifica los precios especiales por volumen.',
            ]);
        }

        if ($syncVolumePrices) {
            BranchVolumePriceService::validateRanges($volumePrices);
        }

        // --- Validación de la regla de negocio del 4% en el backend ---
        // La app lee el historial de precios desde la sucursal matriz o el líder del
        // grupo (igual que en BranchController::fetchBranchProducts), por lo que el
        // nuevo precio también debe registrarse en esa misma sucursal.
        $targetBranch = $this->branchGroups->getProductTargetBranch($branch);

        if (!empty($validated['amount'])) {
            $lastPriceRecord = BranchPriceHistory::where('branch_id', $targetBranch->id)
                ->where('product_id', $product->id)
                ->whereNull('valid_to') // Busca el precio vigente
                ->latest('valid_from')
                ->first();

            // El precio de referencia es el último precio especial o el precio base del producto
            $basePrice = $lastPriceRecord ? $lastPriceRecord->price : $product->base_price;
            $minAllowedPrice = $basePrice * 0.96;

            // Verificamos si el usuario tiene el permiso especial (Spatie)
            $canBypassRule = $request->user() && $request->user()->can('Crear clientes');

            // Si NO tiene el permiso y el precio no cumple con el mínimo requerido, lanzamos el error
            if (!$canBypassRule && $validated['amount'] < $minAllowedPrice) {
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
                'branch_id' => $targetBranch->id,
                'product_id' => $product->id,
                'user_id' => auth()->id(), // Guardamos el usuario que realiza el cambio
                'price' => $validated['amount'],
                'currency' => $validated['currency'],
                'valid_from' => Carbon::parse($validated['valid_from']),
                'valid_to' => null, // null significa que está vigente indefinidamente
            ]);
        }

        // --- Gestión de los precios especiales por volumen ---
        // Se reemplazan los rangos vigentes por los que vienen en la petición.
        if ($syncVolumePrices) {
            $this->volumePrices->sync($targetBranch, $product->id, $volumePrices, auth()->id());
        }

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