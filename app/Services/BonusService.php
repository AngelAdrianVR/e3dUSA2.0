<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\BonusRule;
use App\Models\EmployeeDetail;
use Carbon\Carbon;

class BonusService
{
    public function calculateBonusAmount(Bonus $bonus, EmployeeDetail $employee, array $payrollPeriodData): float
    {
        $baseAmount = $employee->hours_per_week >= 48 ? $bonus->full_time : $bonus->half_time;
        
        if ($bonus->calculation_type === 'proportional_by_day') {
            return $this->calculateProportionalBonus($bonus, $baseAmount, $employee, $payrollPeriodData);
        } else { // 'all_or_nothing_weekly'
            return $this->calculateAllOrNothingBonus($bonus, $baseAmount, $employee, $payrollPeriodData);
        }
    }

    private function calculateAllOrNothingBonus(Bonus $bonus, float $baseAmount, EmployeeDetail $employee, array $payrollPeriodData): float
    {
        foreach ($bonus->rules as $rule) {
            if (!$this->isRuleMet($rule, $employee, $payrollPeriodData)) {
                return 0; // Si una regla falla, el bono es 0.
            }
        }
        return $baseAmount;
    }

    private function calculateProportionalBonus(Bonus $bonus, float $baseAmount, EmployeeDetail $employee, array $payrollPeriodData): float
    {
        $payableDays = collect($payrollPeriodData['week_details'])->filter(fn($day) => $this->isConsideredWorkingDay($day));
        
        if ($payableDays->isEmpty()) {
            return 0;
        }

        $amountPerDay = $baseAmount / $payableDays->count();
        $earnedAmount = 0;

        foreach ($payableDays as $dayData) {
            $dayMetAllRules = true;
            foreach ($bonus->rules as $rule) {
                if (!$this->isRuleMet($rule, $employee, ['week_details' => [$dayData]])) {
                    $dayMetAllRules = false;
                    break;
                }
            }
            
            if ($dayMetAllRules) {
                $earnedAmount += $amountPerDay;
            }
        }
        return $earnedAmount;
    }
    
    private function isConsideredWorkingDay($day): bool
    {
        if (isset($day['incident'])) {
            return !in_array($day['incident']->incidentType->name, ['Descanso', 'Falta injustificada', 'Permiso sin goce', 'Falta justificada']);
        }
        return !empty($day['entry']);
    }

    private function isRuleMet(BonusRule $rule, ?EmployeeDetail $employee, array $data): bool
    {
        $metricValue = $this->getMetricValue($rule->metric, $employee, $data);
        if ($metricValue === null) return false;

        $ruleValue = $rule->value;

        // **NUEVA LÓGICA:** Detectar y procesar valores dinámicos.
        if ($ruleValue === 'employee_scheduled_hours') {
            if (!$employee) return false; // El valor dinámico necesita el contexto del empleado.
            $ruleValue = (float) $employee->hours_per_week;
        } elseif (is_bool($metricValue)) {
            $ruleValue = filter_var($rule->value, FILTER_VALIDATE_BOOLEAN);
        } else {
             $ruleValue = is_numeric($rule->value) ? (float)$rule->value : $rule->value;
        }

        return match ($rule->operator) {
            '==' => $metricValue == $ruleValue,
            '!=' => $metricValue != $ruleValue,
            '>=' => $metricValue >= $ruleValue,
            '<=' => $metricValue <= $ruleValue,
            '>' => $metricValue > $ruleValue,
            '<' => $metricValue < $ruleValue,
            default => false,
        };
    }

    private function getMetricValue(string $metric, ?EmployeeDetail $employee, array $data)
    {
        $weekDetails = collect($data['week_details']);

        return match ($metric) {
            'weekly_unjustified_absences' => $weekDetails->filter(fn($day) => isset($day['incident']) && $day['incident']->incidentType->name === 'Falta injustificada')->count(),
            'weekly_worked_hours' => ($data['summary']['total_worked_seconds'] ?? 0) / 3600,
            'worked_on_sunday' => $weekDetails->contains(fn($day) => !empty($day['entry']) && Carbon::parse($day['date'])->isSunday()),
            'daily_late_minutes' => $weekDetails->first()['late_minutes'] ?? 0,
            default => null,
        };
    }
}