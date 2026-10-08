<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchVolumePrice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BranchVolumePriceService
{
    /**
     * Reglas de validación de los rangos de precio por volumen.
     *
     * @param string $prefix Puede ser 'volume_prices' o una ruta anidada como 'products.*.volume_prices'.
     */
    public static function rules(string $prefix = 'volume_prices'): array
    {
        return [
            "{$prefix}" => 'sometimes|array',
            "{$prefix}.*.min_quantity" => 'required|numeric|min:0.01',
            "{$prefix}.*.max_quantity" => 'nullable|numeric',
            "{$prefix}.*.price" => 'required|numeric|min:0.01',
            "{$prefix}.*.currency" => 'nullable|string|in:MXN,USD',
        ];
    }

    /**
     * Valida que los rangos sean coherentes y no se traslapen entre sí.
     * Lanza ValidationException con mensajes claros para el frontend.
     */
    public static function validateRanges(array $tiers): void
    {
        $tiers = self::sortTiers($tiers);

        if ($tiers->isEmpty()) {
            return;
        }

        $previousMax = null;

        foreach ($tiers as $index => $tier) {
            $min = (float) $tier['min_quantity'];
            $max = self::maxOf($tier);

            if ($max !== null && $max < $min) {
                throw ValidationException::withMessages([
                    'volume_prices' => 'En el rango #' . ($index + 1) . ', la cantidad "hasta" debe ser mayor o igual a la cantidad "desde".',
                ]);
            }

            if ($index > 0) {
                if ($previousMax === null) {
                    throw ValidationException::withMessages([
                        'volume_prices' => 'El rango #' . $index . ' no tiene cantidad máxima (es abierto), por lo que no puede existir otro rango después de él.',
                    ]);
                }

                if ($min <= $previousMax) {
                    throw ValidationException::withMessages([
                        'volume_prices' => 'Los rangos #' . $index . ' y #' . ($index + 1) . ' se traslapan. Revisa las cantidades "desde" y "hasta".',
                    ]);
                }
            }

            $previousMax = $max;
        }
    }

    /**
     * Reemplaza los precios por volumen vigentes de un producto en una sucursal.
     */
    public function sync(Branch $branch, int $productId, array $tiers, ?int $userId): void
    {
        self::validateRanges($tiers);

        BranchVolumePrice::where('branch_id', $branch->id)
            ->where('product_id', $productId)
            ->delete();

        self::sortTiers($tiers)->each(function ($tier) use ($branch, $productId, $userId) {
            BranchVolumePrice::create([
                'branch_id' => $branch->id,
                'product_id' => $productId,
                'user_id' => $userId,
                'min_quantity' => $tier['min_quantity'],
                'max_quantity' => self::maxOf($tier),
                'price' => $tier['price'],
                'currency' => $tier['currency'] ?? 'MXN',
            ]);
        });
    }

    /**
     * Elimina los precios por volumen de un producto en una sucursal.
     */
    public static function deleteFor(Branch $branch, int $productId): void
    {
        BranchVolumePrice::where('branch_id', $branch->id)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * Ordena los rangos por cantidad mínima.
     */
    private static function sortTiers(array $tiers): Collection
    {
        return collect($tiers)
            ->values()
            ->sortBy(fn ($tier) => (float) $tier['min_quantity'])
            ->values();
    }

    /**
     * Normaliza la cantidad máxima: vacía o nula significa "en adelante".
     */
    private static function maxOf(array $tier): ?float
    {
        return isset($tier['max_quantity']) && $tier['max_quantity'] !== null && $tier['max_quantity'] !== ''
            ? (float) $tier['max_quantity']
            : null;
    }
}
