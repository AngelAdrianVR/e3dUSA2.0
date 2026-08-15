<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetail;
use App\Models\SalaryIncrease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalaryIncreaseController extends Controller
{
    /**
     * Registra un aumento salarial en el historial del empleado.
     * Opcionalmente actualiza el salario actual (week_salary) a la nueva cantidad.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_detail_id' => 'required|exists:employee_details,id',
            'previous_amount' => 'required|numeric|min:0',
            'new_amount' => 'required|numeric|min:0|gte:previous_amount',
            'notes' => 'nullable|string|max:1000',
            'increase_date' => 'required|date',
            'update_current_salary' => 'sometimes|boolean',
        ], [
            'new_amount.gte' => 'La nueva cantidad debe ser mayor o igual a la cantidad anterior.',
        ]);

        DB::transaction(function () use ($validated) {
            $data = $validated;
            unset($data['update_current_salary']);

            SalaryIncrease::create($data + ['created_by' => Auth::id()]);

            if (!empty($validated['update_current_salary'])) {
                EmployeeDetail::where('id', $validated['employee_detail_id'])->update([
                    'week_salary' => $validated['new_amount'],
                ]);
            }
        });

        return back()->with('success', 'Aumento salarial registrado correctamente.');
    }

    /**
     * Elimina un registro del historial de aumentos salariales.
     */
    public function destroy(SalaryIncrease $salaryIncrease)
    {
        $salaryIncrease->delete();

        return back()->with('success', 'Registro de aumento eliminado.');
    }
}
