<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BonusController extends Controller
{
    public function index()
    {
        $bonuses = Bonus::with('rules')->latest()->paginate(30);

        // Definición de las reglas y métricas disponibles para el constructor en el frontend.
        $rule_definitions = [
            'metrics' => [
                ['value' => 'daily_late_minutes', 'label' => 'Minutos Tarde (por día)', 'scope' => 'proportional_by_day'],
                ['value' => 'weekly_unjustified_absences', 'label' => 'Faltas Injustificadas (semanal)', 'scope' => 'all_or_nothing_weekly'],
                [
                    'value' => 'weekly_worked_hours',
                    'label' => 'Horas Trabajadas (semanal)',
                    'scope' => 'all_or_nothing_weekly',
                    'accepts_dynamic' => true
                ],
                ['value' => 'worked_on_sunday', 'label' => 'Trabajó en Domingo', 'scope' => 'all_or_nothing_weekly'],
            ],
            'operators' => [
                ['value' => '<=', 'label' => 'Menor o igual que (<=)'],
                ['value' => '>=', 'label' => 'Mayor o igual que (>=)'],
                ['value' => '==', 'label' => 'Igual a (==)'],
                ['value' => '>', 'label' => 'Mayor que (>)'],
                ['value' => '<', 'label' => 'Menor que (<)'],
                ['value' => '!=', 'label' => 'Diferente de (!=)'],
            ]
        ];

        return inertia('Bonus/Index', compact('bonuses', 'rule_definitions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'calculation_type' => 'required|string|in:all_or_nothing_weekly,proportional_by_day',
            'full_time' => 'required|numeric|min:0',
            'half_time' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'rules' => 'nullable|array',
            'rules.*.metric' => 'required|string',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'required|string',
        ]);

        DB::transaction(function () use ($validated) {
            $bonus = Bonus::create($validated);
            if (!empty($validated['rules'])) {
                $bonus->rules()->createMany($validated['rules']);
            }
        });

        return to_route('bonuses.index');
    }

    public function update(Request $request, Bonus $bonus)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'description' => 'nullable',
            'calculation_type' => 'required|string|in:all_or_nothing_weekly,proportional_by_day',
            'full_time' => 'required|numeric|min:0',
            'half_time' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'rules' => 'nullable|array',
            'rules.*.metric' => 'required|string',
            'rules.*.operator' => 'required|string',
            'rules.*.value' => 'required|string',
        ]);

        DB::transaction(function () use ($bonus, $validated) {
            $bonus->update($validated);

            // Sincronizar reglas: eliminar las antiguas y crear las nuevas
            $bonus->rules()->delete();
            if (!empty($validated['rules'])) {
                $bonus->rules()->createMany($validated['rules']);
            }
        });

        return to_route('bonuses.index');
    }

    public function massiveDelete(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        Bonus::whereIn('id', $ids)->delete();
        // Las reglas se eliminan en cascada por la configuración de la BD.
    }
}
