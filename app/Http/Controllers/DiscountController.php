<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index()
    {
        $discounts = Discount::latest()->paginate(30);

        return inertia('Discount/Index', compact('discounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'amount' => 'required|numeric|min:0',
        ]);

        Discount::create($request->all());

        return to_route('discounts.index');
    }

    public function update(Request $request, Discount $discount)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'amount' => 'required|numeric|min:0',
        ]);

        $discount->update($request->all());

        return to_route('discounts.index');
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Discount::find($id);
            $bonus?->delete();
        }
    }
}
