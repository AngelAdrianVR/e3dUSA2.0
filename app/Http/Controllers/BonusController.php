<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    public function index()
    {
        $bonuses = Bonus::latest()->paginate(30);

        return inertia('Bonus/Index', compact('bonuses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'full_time' => 'required|numeric|min:1',
            'half_time' => 'required|numeric|min:1',
        ]);

        Bonus::create($request->all());

        return to_route('bonuses.index');
    }

    public function update(Request $request, Bonus $bonus)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'full_time' => 'required|numeric|min:1',
            'half_time' => 'required|numeric|min:1',
        ]);

        $bonus->update($request->all());

        return to_route('bonuses.index');
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Bonus::find($id);
            $bonus?->delete();
        }
    }

}
