<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetail;
use App\Models\IncidentType;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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

    public function show(Payroll $payroll)
    {
        $employees = EmployeeDetail::with(['user', 'bonuses', 'discounts'])->get();
        $incidentTypes = IncidentType::all();
        $paidIncidentTypes = ['Permiso con goce', 'Falta justificada', 'Incapacidad por trabajo', 'Vacaciones', 'Día festivo', 'Descanso'];

        $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);

        $payrollData = $employees->map(function ($employee) use ($period, $paidIncidentTypes) {
            
            $hourlyRate = ($employee->hours_per_week > 0) ? ($employee->week_salary / $employee->hours_per_week) : 0;
            $dailyHours = ($employee->hours_per_week > 0 && count($employee->work_days) > 0) ? ($employee->hours_per_week / count(array_filter($employee->work_days, fn($day) => $day['works']))) : 0;

            $totalWorkedSeconds = 0;
            $workedDaysCount = 0;
            $totalPayableSeconds = 0;

            $daysData = collect($period->toArray())->map(function ($date) use ($employee, &$totalWorkedSeconds, &$workedDaysCount, &$totalPayableSeconds, $paidIncidentTypes, $dailyHours) {
                $dayString = $date->format('Y-m-d');
                $incident = $employee->incidents()->where('date', $dayString)->with('incidentType')->first();
                $dayWorkedSeconds = 0;

                if ($incident) {
                    if (in_array($incident->incidentType->name, $paidIncidentTypes)) {
                        $totalPayableSeconds += $dailyHours * 3600;
                    }
                } else {
                    $attendances = $employee->attendances()->whereDate('timestamp', $dayString)->orderBy('timestamp', 'asc')->get();
                    $entry = $attendances->firstWhere('type', 'entry');
                    $exit = $attendances->where('type', 'exit')->last();

                    if ($entry && $exit) {
                        $dayWorkedSeconds = Carbon::parse($exit->timestamp)->getTimestamp() - Carbon::parse($entry->timestamp)->getTimestamp();
                        // Aquí se debería restar el tiempo de descansos
                        $totalWorkedSeconds += $dayWorkedSeconds;
                        $totalPayableSeconds += $dayWorkedSeconds;
                        $workedDaysCount++;
                    }
                }
                
                $attendances = $employee->attendances()->whereDate('timestamp', $dayString)->orderBy('timestamp', 'asc')->get();
                $entry = $attendances->firstWhere('type', 'entry');
                $exit = $attendances->where('type', 'exit')->last();
                
                return [
                    'date' => $dayString,
                    'day_name' => ucfirst($date->isoFormat('dddd')),
                    'entry' => $entry ? Carbon::parse($entry->timestamp)->format('H:i:s') : null,
                    'exit' => $exit ? Carbon::parse($exit->timestamp)->format('H:i:s') : null,
                    'total_time' => $dayWorkedSeconds > 0 ? gmdate('H\h i\m', $dayWorkedSeconds) : '0h 0m',
                    'incident' => $incident,
                ];
            });

            $baseSalary = ($totalPayableSeconds / 3600) * $hourlyRate;
            $totalBonuses = $employee->bonuses->sum('amount');
            $totalDiscounts = $employee->discounts->sum('amount');
            $totalToPay = $baseSalary + $totalBonuses - $totalDiscounts;
            
            return [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->user->name,
                    'job_position' => $employee->job_position ?? 'N/A',
                    'hours_per_week' => $employee->hours_per_week ?? 'N/A',
                ],
                'week_details' => $daysData,
                'summary' => [
                    'worked_days' => $workedDaysCount,
                    'total_work_time' => gmdate('H\h i\m', $totalWorkedSeconds),
                    'base_salary' => $baseSalary,
                    'bonuses' => $employee->bonuses->map(fn($b) => ['name' => $b->name, 'amount' => $b->amount]),
                    'total_bonuses' => $totalBonuses,
                    'discounts' => $employee->discounts->map(fn($d) => ['name' => $d->name, 'amount' => $d->amount]),
                    'total_discounts' => $totalDiscounts,
                    'total_to_pay' => $totalToPay,
                ],
            ];
        });

        // Calcular el total general de la nómina
        $grandTotal = $payrollData->sum('summary.total_to_pay');

        return Inertia::render('Payroll/Show', [
            'payroll' => $payroll,
            'payrollData' => $payrollData,
            'incidentTypes' => $incidentTypes,
            'grandTotal' => $grandTotal,
        ]);
    }
}