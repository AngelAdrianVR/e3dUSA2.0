<?php

namespace App\Http\Controllers;

use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index()
    {
        $holidays = Holiday::paginate(30);

        return inertia('Holiday/Index', compact('holidays'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'day' => 'required|numeric|min:1',
            'month' => 'required|numeric|min:1',
        ]);

        Holiday::create([
            'name' => $request->name,
            'date' => "2023-$request->month-$request->day",
            'is_active' => $request->is_active,
        ]);

        return to_route('holidays.index');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $request->validate([
            'name' => 'required|string|max:191',
            'day' => 'required|numeric|min:1',
            'month' => 'required|numeric|min:1',
        ]);

        $holiday->update([
            'name' => $request->name,
            'date' => "2023-$request->month-$request->day",
            'is_active' => $request->is_active,
        ]);

        return to_route('holidays.index');
    }

    // other methods
    public function massiveDelete(Request $request)
    {
        foreach ($request->ids as $id) {
            $bonus = Holiday::find($id);
            $bonus?->delete();
        }
    }
}
