<?php

namespace App\Http\Controllers;

use App\Models\ProductFamily;
use App\Models\ShippingRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShippingRateController extends Controller
{
    public function index()
    {
        // Traemos todas las familias con sus tarifas ordenadas por cantidad,
        // para pintar una tabla (ficha de especificaciones) por familia.
        $families = ProductFamily::with(['shippingRates' => function ($query) {
                $query->orderBy('quantity');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'sat_code']);

        return inertia('ShippingRate/Index', [
            'families' => $families,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        ShippingRate::create($validated);

        return to_route('shipping-rates.index');
    }

    public function update(Request $request, ShippingRate $shippingRate)
    {
        $validated = $request->validate($this->rules($request, $shippingRate));

        $shippingRate->update($validated);

        return to_route('shipping-rates.index');
    }

    public function destroy(ShippingRate $shippingRate)
    {
        $shippingRate->delete();

        return to_route('shipping-rates.index');
    }

    /**
     * Actualiza el código del SAT de la familia (es único por familia).
     */
    public function updateFamilySatCode(Request $request, ProductFamily $productFamily)
    {
        $validated = $request->validate([
            'sat_code' => 'nullable|string|max:20',
        ]);

        $productFamily->update([
            'sat_code' => $validated['sat_code'] ?? null,
        ]);

        return to_route('shipping-rates.index');
    }

    /**
     * Reglas de validación de una tarifa. La cantidad no puede repetirse dentro de la misma familia.
     */
    private function rules(Request $request, ?ShippingRate $shippingRate = null): array
    {
        $quantityRule = Rule::unique('shipping_rates', 'quantity')
            ->where('product_family_id', $request->input('product_family_id'));

        if ($shippingRate) {
            $quantityRule->ignore($shippingRate->id);
        }

        return [
            'product_family_id' => 'required|exists:product_families,id',
            'quantity' => ['required', 'integer', 'min:1', $quantityRule],
            'length_cm' => 'required|numeric|min:0.01',
            'width_cm' => 'required|numeric|min:0.01',
            'height_cm' => 'required|numeric|min:0.01',
            'weight_kg' => 'required|numeric|min:0.01',
        ];
    }
}
