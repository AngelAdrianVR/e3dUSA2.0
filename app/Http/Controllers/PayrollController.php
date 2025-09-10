<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetail;
use App\Models\IncidentType;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index()
    {
        $payrolls = Payroll::orderBy('start_date', 'desc')->get()->map(function ($payroll) {
            return [
                'id' => $payroll->id,
                'week_number' => $payroll->week_number,
                'start_date' => Carbon::parse($payroll->start_date)->format('d/m/Y'),
                'end_date' => Carbon::parse($payroll->end_date)->format('d/m/Y'),
                'status' => $payroll->status,
            ];
        });

        return Inertia::render('Payroll/Index', [
            'payrolls' => $payrolls,
        ]);
    }

    public function show(Request $request, Payroll $payroll)
    {
        $employees = EmployeeDetail::with([
            'user',
            'bonuses',
            'discounts',
            'incidents' => function ($query) use ($payroll) {
                $query->where('payroll_id', $payroll->id)->with('incidentType');
            },
            'attendances' => function ($query) use ($payroll) {
                $query->whereBetween('timestamp', [
                    Carbon::parse($payroll->start_date)->startOfDay(),
                    Carbon::parse($payroll->end_date)->endOfDay()
                ]);
            }
        ])->whereHas('user', function ($query) {
            $query->where('is_active', true);
        })->get();

        $incidentTypes = IncidentType::all();
        $paidIncidentTypes = ['Permiso con goce', 'Vacaciones', 'Día festivo', 'Incapacidad por trabajo'];
        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);

        $payrollData = $this->calculatePayrollData($employees, $period, $paidIncidentTypes);
        $grandTotal = $payrollData->sum('summary.total_to_pay');

        return Inertia::render('Payroll/Show', [
            'payroll' => $payroll,
            'payrollData' => $payrollData,
            'incidentTypes' => $incidentTypes,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Prepara los datos para la vista de impresión.
     */
    public function print(Request $request, Payroll $payroll)
    {
        $request->validate([
            'employees' => 'required|array',
            'employees.*' => 'exists:employee_details,id'
        ]);

        $employeeIds = $request->input('employees');

        $employees = EmployeeDetail::with(['user', 'bonuses', 'discounts', 'incidents', 'attendances'])
            ->whereIn('id', $employeeIds)
            ->get();

        $paidIncidentTypes = ['Permiso con goce', 'Vacaciones', 'Día festivo', 'Incapacidad por trabajo'];
        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);

        $employeesToPrint = $this->calculatePayrollData($employees, $period, $paidIncidentTypes);

        return Inertia::render('Payroll/Print', [
            'payroll' => $payroll,
            'employeesToPrint' => $employeesToPrint,
        ]);
    }

    /**
     * Lógica centralizada para calcular los datos de la nómina.
     */
    private function calculatePayrollData($employees, $period, $paidIncidentTypes)
    {
        return $employees->map(function ($employee) use ($period, $paidIncidentTypes) {
            $hourlyRate = ($employee->hours_per_week > 0) ? ($employee->week_salary / $employee->hours_per_week) : 0;
            $workDaysArray = array_filter($employee->work_days ?? [], fn($day) => $day['works']);
            $dailyHours = ($employee->hours_per_week > 0 && count($workDaysArray) > 0) ? ($employee->hours_per_week / count($workDaysArray)) : 0;
            
            $totalWorkedSeconds = 0;
            $totalPayableSeconds = 0;

            $daysData = collect($period->toArray())->map(function ($date) use ($employee, &$totalWorkedSeconds, &$totalPayableSeconds, $paidIncidentTypes, $dailyHours) {
                $dayString = $date->format('Y-m-d');
                $incident = $employee->incidents->firstWhere('date', $dayString);
                
                $attendances = $employee->attendances
                    ->filter(fn($att) => Carbon::parse($att->timestamp)->isSameDay($date))
                    ->sortBy('timestamp');

                $entry = $attendances->firstWhere('type', 'entry');
                $exit = $attendances->where('type', 'exit')->last();
                
                $dayWorkedSeconds = 0;
                $totalBreakSeconds = 0;
                $breaksBreakdown = [];

                if ($incident) {
                    if (in_array($incident->incidentType->name, $paidIncidentTypes)) {
                        $totalPayableSeconds += $dailyHours * 3600;
                    }
                } elseif ($entry && $exit) {
                    $dayWorkedSeconds = Carbon::parse($exit->timestamp)->getTimestamp() - Carbon::parse($entry->timestamp)->getTimestamp();
                    
                    $breaks = $attendances->whereIn('type', ['start_break', 'end_break'])->values();
                    for ($i = 0; $i < $breaks->count(); $i += 2) {
                        if (isset($breaks[$i]) && $breaks[$i]->type === 'start_break' && isset($breaks[$i+1]) && $breaks[$i+1]->type === 'end_break') {
                            $breakStart = Carbon::parse($breaks[$i]->timestamp);
                            $breakEnd = Carbon::parse($breaks[$i+1]->timestamp);
                            $breakDuration = $breakEnd->getTimestamp() - $breakStart->getTimestamp();
                            $totalBreakSeconds += $breakDuration;
                            $breaksBreakdown[] = ['start' => $breakStart->toTimeString(), 'end' => $breakEnd->toTimeString()];
                        }
                    }
                    $dayWorkedSeconds -= $totalBreakSeconds;

                    $totalWorkedSeconds += $dayWorkedSeconds;
                    $totalPayableSeconds += $dayWorkedSeconds;
                }

                return [
                    'date' => $dayString,
                    'day_name' => ucfirst(Carbon::parse($date)->isoFormat('dddd')),
                    'entry' => $entry ? $entry->timestamp->format('H:i:s') : null,
                    'entry_id' => $entry->id ?? null,
                    'late_minutes' => $entry->late_minutes ?? 0,
                    'ignore_late' => $entry->ignore_late ?? false,
                    'exit' => $exit ? $exit->timestamp->format('H:i:s') : null,
                    'total_time' => $dayWorkedSeconds > 0 ? gmdate('H\h i\m', $dayWorkedSeconds) : '0h 0m',
                    'total_break_time' => $totalBreakSeconds > 0 ? gmdate('H\h i\m', $totalBreakSeconds) : '0h 0m',
                    'breaks_breakdown' => $breaksBreakdown,
                    'incident' => $incident,
                ];
            });

            $baseSalary = ($totalPayableSeconds / 3600) * $hourlyRate;
            $bonusesTotal = $employee->bonuses->sum('amount');
            $discountsTotal = $employee->discounts->sum('amount');
            $totalToPay = $baseSalary + $bonusesTotal - $discountsTotal;

            return [
                'employee' => $employee->toArray() + [
                    'name' => $employee->user->name,
                    'total_worked_seconds' => $totalWorkedSeconds // <-- FIX: Adding this value back
                ],
                'week_details' => $daysData,
                'summary' => [
                    'base_salary' => $baseSalary,
                    'bonuses' => $employee->bonuses,
                    'discounts' => $employee->discounts,
                    'total_to_pay' => $totalToPay,
                ],
            ];
        });
    }
}
