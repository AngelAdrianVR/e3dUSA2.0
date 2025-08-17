<?php

namespace App\Http\Controllers;

use App\Models\ProductionCost;
use Illuminate\Http\Request;

class ProductionCostController extends Controller
{
    public function index()
    {
        $production_costs = ProductionCost::latest()->paginate(30);

        return inertia('ProductionCost/Index', compact('production_costs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'cost_type' => 'required|string',
            'estimated_time_seconds' => 'required|numeric|min:1|max:99999',
            'cost' => 'required|numeric|min:0',
        ]);

        ProductionCost::create($request->all());

        return to_route('production-costs.index');
    }

    public function show(ProductionCost $production_cost)
    {
        //
    }

    public function update(Request $request, ProductionCost $production_cost)
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'cost_type' => 'required|string',
            'estimated_time_seconds' => 'required|numeric|min:1|max:99999',
            'cost' => 'required|numeric|min:0',
        ]);

        $production_cost->update($request->all());

        return to_route('production-costs.index');
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = ProductionCost::find($id);
            $bonus?->delete();
        }
    }
}
