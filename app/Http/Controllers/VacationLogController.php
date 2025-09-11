<?php

namespace App\Http\Controllers;

use App\Models\VacationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VacationLogController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_detail_id' => 'required|exists:employee_details,id',
            'type' => 'required|in:initial_balance,manual_adjustment,earned',
            'days' => 'required|numeric',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date',
        ]);

        // Si es un saldo inicial, asegurarse de que no exista otro.
        if ($validated['type'] === 'initial_balance') {
            $existingInitial = VacationLog::where('employee_detail_id', $validated['employee_detail_id'])
                ->where('type', 'initial_balance')
                ->exists();

            if ($existingInitial) {
                return back()->withErrors(['type' => 'Ya existe un saldo inicial para este empleado. Utilice un ajuste manual.']);
            }
        }

        VacationLog::create($validated + ['created_by' => Auth::id()]);

        return back()->with('success', 'Movimiento de vacaciones registrado correctamente.');
    }
}