<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EmployeeDetail;
use App\Models\Holiday;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
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
        $holidayType = IncidentType::where('name', 'Día festivo')->first();
        $bonusService = new BonusService();

        $holidays = Holiday::where('is_active', true)->get()->keyBy(fn($holiday) => Carbon::parse($holiday->date)->format('m-d'));

        return $employees->map(function ($employee) use ($payroll, $unjustifiedAbsenceType, $dayOffType, $holidayType, $holidays, $bonusService) {
            $period = CarbonPeriod::create($payroll->start_date, $payroll->end_date);
            $totalWorkedSeconds = 0;
            $vacationPremium = 0;
            $extraHolidayPay = 0;
            $daysData = [];

            // Pre-cargar datos para optimizar
            $allAttendances = Attendance::where('employee_detail_id', $employee->id)->whereBetween('timestamp', [$payroll->start_date, Carbon::parse($payroll->end_date)->endOfDay()])->orderBy('timestamp')->get();
            $allIncidents = Incident::where('employee_detail_id', $employee->id)->whereBetween('date', [$payroll->start_date, $payroll->end_date])->with('incidentType')->get();

            $approvedOvertime = OvertimeRequest::where('employee_detail_id', $employee->id)
                ->where('status', 'approved')
                ->whereBetween('date', [$payroll->start_date, $payroll->end_date])
                ->get()->keyBy(fn($request) => $request->date->format('Y-m-d'));
            $totalApprovedOvertimeSeconds = $approvedOvertime->sum('requested_minutes') * 60;

            foreach ($period as $date) {
                Carbon::setLocale('es');
                $dayString = $date->format('Y-m-d');
                $dayName = ucfirst($date->isoFormat('dddd'));
                $monthDay = $date->format('m-d');
                $isHoliday = $holidays->has($monthDay);
                $dayAttendances = $allAttendances->where('timestamp', '>=', $dayString . ' 00:00:00')->where('timestamp', '<=', $dayString . ' 23:59:59');
                $incident = $allIncidents->firstWhere('date', $dayString);

                $dayData = [
                    'date' => $dayString,
                    'day_name' => $dayName,
                    'incident' => $incident,
                    'worked_on_holiday' => false,
                    'entry' => null,
                    'exit' => null,
                    'total_break_time' => '0h 0m',
                    'total_time' => '0h 0m',
                    'late_minutes' => 0,
                    'ignore_late' => false,
                    'entry_id' => null,
                    'breaks_details' => [],
                    'unauthorized_overtime_seconds' => 0,
                    'approved_overtime_day_seconds' => 0,
                ];

                if ($payroll->status === 'Abierta' && !$incident && !$dayAttendances->count()) {
                    $workDayConfig = collect($employee->work_days)->firstWhere('day', $dayName);
                    $detectedIncidentType = null;
                    if ($isHoliday) {
                        $detectedIncidentType = $holidayType;
                    } elseif ($date->isPast() && !$date->isToday()) {
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

                $salaryPerHour = $employee->hours_per_week > 0 ? $employee->week_salary / $employee->hours_per_week : 0;

                if ($dayData['incident']) {
                    if ($dayData['incident']->incidentType->name === 'Día festivo') {
                        $workDayConfig = collect($employee->work_days)->firstWhere('day', $dayName);
                        if ($workDayConfig && $workDayConfig['works']) {
                            $startTime = Carbon::parse($workDayConfig['start_time']);
                            $endTime = Carbon::parse($workDayConfig['end_time']);
                            $breakMinutes = $workDayConfig['break_minutes'] ?? 0;
                            $scheduledSeconds = $startTime->diffInSeconds($endTime) - ($breakMinutes * 60);
                            $totalWorkedSeconds += max(0, $scheduledSeconds);
                        }
                    }
                } else {
                    if ($entry && $exit) {
                        $workDayConfig = collect($employee->work_days)->firstWhere('day', $dayName);

                        $scheduledSeconds = 0;
                        if ($workDayConfig && $workDayConfig['works']) {
                            $startTime = Carbon::parse($workDayConfig['start_time']);
                            $endTime = Carbon::parse($workDayConfig['end_time']);
                            $breakMinutes = $workDayConfig['break_minutes'] ?? 0;
                            $scheduledSeconds = $startTime->diffInSeconds($endTime) - ($breakMinutes * 60);
                        }

                        $rawWorkedSeconds = Carbon::parse($entry->timestamp)->diffInSeconds(Carbon::parse($exit->timestamp)) - $breakSeconds;
                        $dayWorkedSeconds = max(0, $rawWorkedSeconds);

                        $approvedOvertimeDaySeconds = 0;
                        if (isset($approvedOvertime[$dayString])) {
                            $approvedOvertimeDaySeconds = $approvedOvertime[$dayString]->requested_minutes * 60;
                            $dayData['approved_overtime_day_seconds'] = $approvedOvertimeDaySeconds;
                        }

                        $payableSeconds = min($dayWorkedSeconds, $scheduledSeconds + $approvedOvertimeDaySeconds);
                        $dayData['unauthorized_overtime_seconds'] = max(0, $dayWorkedSeconds - ($scheduledSeconds + $approvedOvertimeDaySeconds));

                        $totalWorkedSeconds += $payableSeconds;
                        $dayData['total_time'] = gmdate('G\h i\m', $dayWorkedSeconds);

                        if ($isHoliday) {
                            $dayData['worked_on_holiday'] = true;
                            $extraHolidayPay += ($payableSeconds / 3600) * $salaryPerHour;
                        }
                    }
                }
                $daysData[] = $dayData;
            }

            $payrollPeriodData = ['week_details' => $daysData, 'summary' => ['total_worked_seconds' => $totalWorkedSeconds]];
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

            $workedTimeSalary = ($totalWorkedSeconds / 3600) * $salaryPerHour;
            $totalToPay = $workedTimeSalary + $extraHolidayPay;

            foreach ($daysData as $day) {
                if ($day['incident']) {
                    $incidentName = $day['incident']->incidentType->name;
                    switch ($incidentName) {
                        case 'Incapacidad general':
                            $totalToPay += $dailySalary * 0.60;
                            break;
                        case 'Incapacidad por trabajo':
                        case 'Permiso con goce':
                        case 'Día festivo':
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
            $totalToPay += $earnedBonuses->sum('amount');
            $discounts = $employee->discounts->map(fn($discount) => ['name' => $discount->name, 'amount' => $discount->amount]);
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
                    'total_approved_overtime_seconds' => $totalApprovedOvertimeSeconds,
                    'base_salary' => $workedTimeSalary,
                    'extra_holiday_pay' => $extraHolidayPay > 0 ? $extraHolidayPay : null,
                    'bonuses' => $earnedBonuses,
                    'discounts' => $discounts,
                    'vacation_premium' => $vacationPremium > 0 ? $vacationPremium : null,
                    'total_to_pay' => max(0, $totalToPay),
                ],
            ];
        });
    }
}
