<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Sale;
use App\Models\SaleProduct;

class ShippingRateSuggestionService
{
    /**
     * Calcula las tarifas de envío sugeridas para una orden de venta.
     *
     * Agrupa las piezas de la orden por familia de producto y, por cada familia
     * que tenga tarifas registradas, calcula la combinación de cajas que usa el
     * MENOR número de cajas (y, en caso de empate, la que menos espacio sobra).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forSale(Sale $sale): array
    {
        // 1. Piezas por familia de producto.
        // Se consulta aquí mismo para no depender de qué columnas del producto
        // haya cargado el controlador que renderiza la vista.
        $items = SaleProduct::where('sale_id', $sale->id)
            ->whereNotNull('product_id')
            ->get(['product_id', 'quantity']);

        if ($items->isEmpty()) {
            return [];
        }

        $products = Product::with('parent:id,product_family_id')
            ->whereIn('id', $items->pluck('product_id')->unique()->values())
            ->get(['id', 'product_family_id', 'parent_id']);

        $familyByProduct = [];

        foreach ($products as $product) {
            $familyByProduct[$product->id] = $product->product_family_id
                ?? $product->parent?->product_family_id;
        }

        $piecesByFamily = [];

        foreach ($items as $item) {
            $familyId = $familyByProduct[$item->product_id] ?? null;
            if (!$familyId) {
                continue; // Producto sin familia asignada
            }

            $piecesByFamily[$familyId] = ($piecesByFamily[$familyId] ?? 0) + (int) $item->quantity;
        }

        if (empty($piecesByFamily)) {
            return [];
        }

        // 2. Tarifas de las familias involucradas en la orden
        $families = ProductFamily::with(['shippingRates' => function ($query) {
                $query->orderByDesc('quantity');
            }])
            ->whereIn('id', array_keys($piecesByFamily))
            ->get();

        $suggestions = [];

        foreach ($families as $family) {
            $pieces = $piecesByFamily[$family->id];
            if ($pieces <= 0) {
                continue;
            }

            $rates = $family->shippingRates;

            $suggestion = [
                'product_family_id' => $family->id,
                'family_name' => $family->name,
                'sat_code' => $family->sat_code,
                'pieces' => $pieces,
                'total_boxes' => 0,
                'total_weight_kg' => 0,
                'boxes' => [],
                'has_rates' => $rates->isNotEmpty(),
                // Todas las tarifas registradas de la familia (para consultarlas en la orden)
                'available_rates' => $rates->map(fn ($rate) => [
                    'quantity' => (int) $rate->quantity,
                    'length_cm' => (float) $rate->length_cm,
                    'width_cm' => (float) $rate->width_cm,
                    'height_cm' => (float) $rate->height_cm,
                    'weight_kg' => (float) $rate->weight_kg,
                ])->values()->all(),
            ];

            // Familia sin ficha de especificaciones: se avisa al usuario
            if ($rates->isEmpty()) {
                $suggestions[] = $suggestion;
                continue;
            }

            $distribution = $this->calculateBoxes(
                $pieces,
                $rates->pluck('quantity')->map(fn ($q) => (int) $q)->all()
            );

            $boxes = [];

            foreach ($distribution as $boxQuantity => $count) {
                $rate = $rates->firstWhere('quantity', $boxQuantity);
                if (!$rate) {
                    continue;
                }

                $boxes[] = [
                    'quantity' => (int) $rate->quantity,
                    'count' => (int) $count,
                    'length_cm' => (float) $rate->length_cm,
                    'width_cm' => (float) $rate->width_cm,
                    'height_cm' => (float) $rate->height_cm,
                    'weight_kg' => (float) $rate->weight_kg,
                    'total_weight_kg' => round($count * (float) $rate->weight_kg, 2),
                ];
            }

            // Cajas más grandes primero
            usort($boxes, fn ($a, $b) => $b['quantity'] <=> $a['quantity']);

            $suggestion['boxes'] = $boxes;
            $suggestion['total_boxes'] = array_sum(array_column($boxes, 'count'));
            $suggestion['total_weight_kg'] = round(array_sum(array_column($boxes, 'total_weight_kg')), 2);

            $suggestions[] = $suggestion;
        }

        usort($suggestions, fn ($a, $b) => strcmp($a['family_name'], $b['family_name']));

        return $suggestions;
    }

    /**
     * Calcula cuántas cajas de cada tamaño se necesitan.
     *
     * Devuelve [cantidad_por_caja => número_de_cajas] minimizando primero el
     * número total de cajas y luego el espacio sobrante (cajas incompletas).
     *
     * Es público para poder probar el cálculo de forma aislada.
     *
     * @param  array<int, int>  $capacities
     * @return array<int, int>
     */
    public function calculateBoxes(int $pieces, array $capacities): array
    {
        $capacities = array_values(array_unique(array_filter($capacities, fn ($c) => (int) $c > 0)));

        if (empty($capacities) || $pieces <= 0) {
            return [];
        }

        sort($capacities);

        // Salvaguarda por si algún pedido es enorme: solución voraz (de mayor a menor)
        if ($pieces > 100000) {
            return $this->greedyDistribution($pieces, $capacities);
        }

        $maxCapacity = max($capacities);
        $limit = $pieces + $maxCapacity;
        $infinity = PHP_INT_MAX;

        // dp[c] = mínimo número de cajas para cubrir EXACTAMENTE c piezas
        $dp = array_fill(0, $limit + 1, $infinity);
        $dp[0] = 0;

        for ($current = 1; $current <= $limit; $current++) {
            foreach ($capacities as $capacity) {
                if ($capacity <= $current && $dp[$current - $capacity] !== $infinity) {
                    $candidate = $dp[$current - $capacity] + 1;
                    if ($candidate < $dp[$current]) {
                        $dp[$current] = $candidate;
                    }
                }
            }
        }

        // Buscamos la capacidad >= piezas con menos cajas (y menos sobrante)
        $bestCapacity = null;
        $bestBoxes = $infinity;
        $bestExcess = $infinity;

        for ($current = $pieces; $current <= $limit; $current++) {
            if ($dp[$current] === $infinity) {
                continue;
            }

            $excess = $current - $pieces;

            if ($dp[$current] < $bestBoxes || ($dp[$current] === $bestBoxes && $excess < $bestExcess)) {
                $bestCapacity = $current;
                $bestBoxes = $dp[$current];
                $bestExcess = $excess;
            }
        }

        if ($bestCapacity === null) {
            return [];
        }

        return $this->reconstructDistribution($dp, $capacities, $bestCapacity);
    }

    /**
     * Reconstruye la combinación de cajas a partir de la tabla dp.
     *
     * @param  array<int, int>  $dp
     * @param  array<int, int>  $capacities
     * @return array<int, int>
     */
    private function reconstructDistribution(array $dp, array $capacities, int $capacity): array
    {
        $distribution = [];
        $current = $capacity;

        while ($current > 0) {
            foreach ($capacities as $boxCapacity) {
                if ($boxCapacity <= $current
                    && $dp[$current - $boxCapacity] !== PHP_INT_MAX
                    && $dp[$current - $boxCapacity] + 1 === $dp[$current]) {
                    $distribution[$boxCapacity] = ($distribution[$boxCapacity] ?? 0) + 1;
                    $current -= $boxCapacity;
                    continue 2;
                }
            }

            break; // No debería ocurrir
        }

        return $distribution;
    }

    /**
     * Solución voraz usada solo para pedidos gigantes (más de 100,000 piezas).
     *
     * @param  array<int, int>  $capacities
     * @return array<int, int>
     */
    private function greedyDistribution(int $pieces, array $capacities): array
    {
        rsort($capacities);

        $distribution = [];
        $remaining = $pieces;

        foreach ($capacities as $capacity) {
            $count = intdiv($remaining, $capacity);
            if ($count > 0) {
                $distribution[$capacity] = $count;
                $remaining -= $count * $capacity;
            }
        }

        if ($remaining > 0) {
            $smallest = min($capacities);
            $distribution[$smallest] = ($distribution[$smallest] ?? 0) + 1;
        }

        return $distribution;
    }
}
