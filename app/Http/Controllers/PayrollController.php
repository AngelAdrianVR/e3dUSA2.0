<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EmployeeDetail;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Payroll;
use App\Models\User;
use App\Services\BonusService;
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

    public function show(Payroll $payroll)
    {
        // Eager load all necessary relationships, including the bonus rules.
        $employees = EmployeeDetail::with('user', 'bonuses.rules', 'discounts')->get();
        $payrollData = $this->calculatePayrollData($payroll, $employees);

        $grandTotal = $payrollData->sum(function ($data) {
            return $data['summary']['total_to_pay'] ?? 0;
        });

        return Inertia::render('Payroll/Show', [
            'payroll' => $payroll,
            'payrollData' => $payrollData,
            'incidentTypes' => IncidentType::all(),
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Prepara los datos para la vista de impresión.
     */
    public function print(Request $request, Payroll $payroll)
    {
        $employeeIds = $request->input('employees', []);
        $employees = EmployeeDetail::whereIn('id', $employeeIds)->with('user', 'bonuses', 'discounts')->get();
        $payrollData = $this->calculatePayrollData($payroll, $employees);

        return Inertia::render('Payroll/Print', [
            'payroll' => $payroll,
            'employeesToPrint' => $payrollData,
        ]);
    }

    /**
     * Lógica centralizada para calcular los datos de la nómina.
     */
    private function calculatePayrollData(Payroll $payroll, $employees)
    {
        $unjustifiedAbsenceType = IncidentType::where('name', 'Falta injustificada')->first();
        $dayOffType = IncidentType::where('name', 'Descanso')->first();
        $bonusService = new BonusService(); // Instanciar el motor de reglas de bonos.

        return $employees->map(function ($employee) use ($payroll, $unjustifiedAbsenceType, $dayOffType, $bonusService) {
            $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);
            $totalWorkedSeconds = 0;
            $vacationPremium = 0;
            $daysData = [];

            $allAttendances = Attendance::where('employee_detail_id', $employee->id)
                ->whereBetween('timestamp', [$payroll->start_date, Carbon::parse($payroll->end_date)->endOfDay()])
                ->orderBy('timestamp')
                ->get();

            $allIncidents = Incident::where('employee_detail_id', $employee->id)
                ->whereBetween('date', [$payroll->start_date, $payroll->end_date])
                ->with('incidentType')
                ->get();

            foreach ($period as $date) {
                Carbon::setLocale('es');
                $dayString = $date->format('Y-m-d');
                $dayName = ucfirst($date->isoFormat('dddd'));
                $dayAttendances = $allAttendances->where('timestamp', '>=', $dayString . ' 00:00:00')->where('timestamp', '<=', $dayString . ' 23:59:59');
                $incident = $allIncidents->firstWhere('date', $dayString);

                $dayData = [
                    'date' => $dayString,
                    'day_name' => $dayName,
                    'incident' => $incident,
                    'entry' => null,
                    'exit' => null,
                    'total_break_time' => '0h 0m',
                    'total_time' => '0h 0m',
                    'late_minutes' => 0,
                    'ignore_late' => false,
                    'entry_id' => null,
                    'breaks_details' => []
                ];

                if ($payroll->status === 'Abierta' && !$incident && !$dayAttendances->count()) {
                    $workDayConfig = collect($employee->work_days)->firstWhere('day', $dayName);
                    $detectedIncidentType = null;
                    if ($date->isPast() && !$date->isToday()) {
                        if ($workDayConfig && $workDayConfig['works']) {
                            $detectedIncidentType = $unjustifiedAbsenceType;
                        } elseif ($workDayConfig && !$workDayConfig['works']) {
                            $detectedIncidentType = $dayOffType;
                        }
                    }
                    if ($detectedIncidentType) {
                        $dayData['incident'] = new Incident(['date' => $dayString]);
                        $dayData['incident']->setRelation('incidentType', $detectedIncidentType);
                    }
                }

                $entry = $dayAttendances->firstWhere('type', 'entry');
                $exit = $dayAttendances->where('type', 'exit')->last();

                if ($entry) {
                    $dayData['entry'] = $entry->timestamp->format('H:i:s');
                    $dayData['late_minutes'] = $entry->late_minutes ?? 0;
                    $dayData['ignore_late'] = $entry->ignore_late ?? false;
                    $dayData['entry_id'] = $entry->id;
                }
                if ($exit) $dayData['exit'] = $exit->timestamp->format('H:i:s');

                $breakSeconds = 0;
                $breakStarts = $dayAttendances->where('type', 'start_break')->values();
                $breakEnds = $dayAttendances->where('type', 'end_break')->values();
                for ($i = 0; $i < $breakStarts->count(); $i++) {
                    if (isset($breakEnds[$i])) {
                        $start = Carbon::parse($breakStarts[$i]->timestamp);
                        $end = Carbon::parse($breakEnds[$i]->timestamp);
                        $diff = $start->diffInSeconds($end);
                        $breakSeconds += $diff;
                        $dayData['breaks_details'][] = ['start' => $start->format('H:i'), 'end' => $end->format('H:i'), 'total' => floor($diff / 60) . 'm'];
                    }
                }
                if ($breakSeconds > 0) $dayData['total_break_time'] = gmdate('G\h i\m', $breakSeconds);

                // El tiempo trabajado solo se calcula si NO hay una incidencia para el día.
                if (!$dayData['incident']) {
                    if ($entry && $exit) {
                        $worked = Carbon::parse($entry->timestamp)->diffInSeconds(Carbon::parse($exit->timestamp));
                        $dayWorkedSeconds = max(0, $worked - $breakSeconds);
                        $totalWorkedSeconds += $dayWorkedSeconds;
                        $dayData['total_time'] = gmdate('G\h i\m', $dayWorkedSeconds);
                    }
                }

                $daysData[] = $dayData;
            }

            // Estructura de datos que el BonusService necesita para evaluar las reglas.
            $payrollPeriodData = [
                'week_details' => $daysData,
                'summary' => [
                    'total_worked_seconds' => $totalWorkedSeconds,
                ]
            ];
            
            // Calcular el monto ganado para cada bono asignado al empleado.
            $earnedBonuses = collect();
            foreach ($employee->bonuses as $bonus) {
                if ($bonus->is_active) {
                    $earnedAmount = $bonusService->calculateBonusAmount($bonus, $employee, $payrollPeriodData);
                    if ($earnedAmount > 0) {
                        $earnedBonuses->push(['name' => $bonus->name, 'amount' => $earnedAmount]);
                    }
                }
            }

            $baseSalary = $employee->week_salary;
            $workingDays = array_filter($employee->work_days, fn($day) => $day['works']);
            $dailySalary = count($workingDays) > 0 ? $baseSalary / count($workingDays) : 0;
            $totalToPay = 0;

            $salaryPerHour = $employee->hours_per_week > 0 ? $baseSalary / $employee->hours_per_week : 0;
            $workedTimeSalary = ($totalWorkedSeconds / 3600) * $salaryPerHour;
            $totalToPay += $workedTimeSalary;

            foreach ($daysData as $day) {
                if ($day['incident']) {
                    $incidentName = $day['incident']->incidentType->name;
                    switch ($incidentName) {
                        case 'Incapacidad general':
                            $totalToPay += $dailySalary * 0.60;
                            break;
                        case 'Incapacidad por trabajo': case 'Permiso con goce': case 'Día festivo':
                            $totalToPay += $dailySalary;
                            break;
                        case 'Vacaciones':
                            $totalToPay += $dailySalary;
                            $vacationPremium += $dailySalary * 0.25;
                            break;
                    }
                }
            }
            $totalToPay += $vacationPremium;
            
            $discounts = $employee->discounts->map(fn($discount) => ['name' => $discount->name, 'amount' => $discount->amount]);
            
            $totalToPay += $earnedBonuses->sum('amount');
            $totalToPay -= $discounts->sum('amount');

            return [
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->user->name,
                    'job_position' => $employee->job_position,
                    'hours_per_week' => $employee->hours_per_week,
                ],
                'week_details' => $daysData,
                'summary' => [
                    'total_worked_seconds' => $totalWorkedSeconds,
                    'base_salary' => $workedTimeSalary,
                    'bonuses' => $earnedBonuses,
                    'discounts' => $discounts,
                    'vacation_premium' => $vacationPremium > 0 ? $vacationPremium : null,
                    'total_to_pay' => max(0, $totalToPay),
                ],
            ];
        });
    }
}
