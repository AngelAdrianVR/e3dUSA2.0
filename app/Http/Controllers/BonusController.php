<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    public function index()
    {
        $bonuses = Bonus::all();

        return inertia('Bonus/Index', compact('bonuses'));
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'full_time' => 'required|numeric|min:1',
            'half_time' => 'required|numeric|min:1',
        ]);

        $bonus = Bonus::create($request->all());
        
        // // Tegistrar el evento de creación en historial
        // event(new RecordCreated($bonus));

        return to_route('bonuses.index');
    }

    public function show(Bonus $bonus)
    {
        //
    }

    public function edit(Bonus $bonus)
    {
        //
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

        // // Registrar el evento de edición en historial
        // event(new RecordEdited($bonus));

        return to_route('bonuses.index');
    }

    public function destroy(Bonus $bonus)
    {
        //
    }

    public function massiveDelete(Request $request)
    {
        foreach ($request->bonuses as $bonus) {
            $bonus = Bonus::find($bonus['id']);
            $bonus?->delete();

            // event(new RecordDeleted($bonus));
        }

        return response()->json(['message' => 'Bono(s) eliminado(s)']);
    }
}
