<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\Branch;
use App\Models\ChMessage;
use App\Models\Discount;
use App\Models\Payroll;
use App\Models\TerminationLog;
use App\Models\User;
use App\Models\VacationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Obtenemos el término de búsqueda y los filtros existentes
        $filters = $request->only('search');

        $users = User::query()
            // Aplicamos el filtro solo si 'search' tiene un valor (búsqueda desde la vista)
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");

                    // Permite buscar por "activo" o "inactivo"
                    if (strtolower($search) === 'activo') {
                        $subQuery->orWhere('is_active', 1);
                    } elseif (strtolower($search) === 'inactivo') {
                        $subQuery->orWhere('is_active', 0);
                    }
                });
            })
            ->where('id', '>', 3)
            ->latest() // Opcional: ordenar por los más recientes
            ->paginate(30)
            // Importante: mantiene los parámetros de búsqueda en los enlaces de paginación
            ->withQueryString();

        return inertia('User/Index', [
            'users' => $users,
            'filters' => $filters, // Pasamos los filtros a la vista
        ]);
    }

    public function create()
    {
        return Inertia::render('User/Create', [
            'roles' => Role::all(),
            'bonuses' => Bonus::all(),
            'discounts' => Discount::all(),
        ]);
    }

    public function show(User $user)
    {
        $user->load('employeeDetail.bonuses', 'employeeDetail.discounts', 'roles');

        $vacationLogs = collect();
        $workYears = [];
        $age = null;
        $seniority = null;

        if ($user->employeeDetail) {
            $vacationLogs = VacationLog::with('creator:id,name')
                ->where('employee_detail_id', $user->employeeDetail->id)
                ->latest('date')
                ->get();

            if ($user->employeeDetail->birthdate) {
                $age = Carbon::parse($user->employeeDetail->birthdate)->age;
            }
            if ($user->employeeDetail->join_date) {
                $seniority = Carbon::parse($user->employeeDetail->join_date)->diffInYears(Carbon::now());
                $workYears = $this->buildWorkYears($user->employeeDetail->join_date, $vacationLogs);
            } elseif ($vacationLogs->isNotEmpty()) {
                // Si no hay fecha de ingreso pero sí hay logs de vacaciones,
                // usamos la fecha del log más antiguo como referencia
                $earliestLogDate = $vacationLogs->min('date');
                $workYears = $this->buildWorkYears($earliestLogDate, $vacationLogs);
            }
        }

        // Cargar el historial de bajas
        $terminationLogs = TerminationLog::with('terminator:id,name')
            ->where('user_id', $user->id)
            ->latest('termination_date')
            ->get();

        $totalVacations = $vacationLogs->sum('days');
        $takenVacations = $vacationLogs->where('type', 'taken')->sum('days');

        return inertia('User/Show', [
            'user' => $user,
            'vacation_logs' => $vacationLogs,
            'work_years' => $workYears,
            'termination_logs' => $terminationLogs,
            'vacation_summary' => [
                'available' => $totalVacations,
                'taken' => abs($takenVacations),
            ],
            'age' => number_format($age, 0),
            'seniority' => number_format($seniority, 2),
        ]);
    }

    /**
     * Muestra la vista de finiquito para un usuario.
     */
    public function settlement(User $user)
    {
        $user->load('employeeDetail');

        if (!$user->employeeDetail || !$user->employeeDetail->join_date) {
            return redirect()->route('users.show', $user)->with('error', 'El empleado no tiene fecha de ingreso registrada.');
        }

        $employee = $user->employeeDetail;
        $joinDate = Carbon::parse($employee->join_date);
        $now = Carbon::now();

        // Días laborales y salario diario
        $workingDays = array_filter($employee->work_days ?? [], fn($d) => $d['works']);
        $workingDaysCount = count($workingDays);
        $dailySalary = $workingDaysCount > 0 ? $employee->week_salary / $workingDaysCount : 0;

        // ---- Vacaciones pendientes ----
        $vacationLogs = VacationLog::where('employee_detail_id', $employee->id)->latest('date')->get();
        $workYears = $this->buildWorkYears($employee->join_date, $vacationLogs);
        $currentPeriod = collect($workYears)->firstWhere('is_current', true) ?? collect($workYears)->first();
        $availableDays = $currentPeriod['available_days'] ?? 0;
        $vacationPay = max(0, $availableDays * $dailySalary);
        $vacationPremium = $vacationPay * 0.25;

        // ---- Parte proporcional de aguinaldo ----
        $lastAnniversary = Carbon::create($now->year, $joinDate->month, $joinDate->day);
        if ($lastAnniversary->gt($now)) {
            $lastAnniversary->subYear();
        }
        $daysSinceAnniversary = $lastAnniversary->diffInDays($now);
        $aguinaldoProportional = 15 * $dailySalary * ($daysSinceAnniversary / 365);

        // ---- Nómina pendiente (periodo abierto actual) ----
        $openPayroll = Payroll::where('status', 'Abierta')->first();
        $pendingPayroll = 0;
        $payrollWeek = null;
        if ($openPayroll) {
            $payrollController = app(PayrollController::class);
            $payrollData = $payrollController->calculatePayrollData($openPayroll, collect([$employee]));
            $pendingPayroll = $payrollData->first()['summary']['total_to_pay'] ?? 0;
            $payrollWeek = [
                'start_date' => $openPayroll->start_date,
                'end_date'   => $openPayroll->end_date,
            ];
        }

        // ---- Totales ----
        $totalSettlement = $vacationPay + $vacationPremium + $aguinaldoProportional + $pendingPayroll;

        return inertia('User/Settlement', [
            'user' => $user,
            'settlement' => [
                'daily_salary'           => round($dailySalary, 2),
                'join_date'              => $joinDate->toDateString(),
                'current_date'           => $now->toDateString(),
                'years_of_service'       => $joinDate->diffInYears($now),
                'available_vacation_days' => round($availableDays, 2),
                'vacation_pay'            => round($vacationPay, 2),
                'vacation_premium'        => round($vacationPremium, 2),
                'days_since_anniversary'  => $daysSinceAnniversary,
                'aguinaldo_proportional'  => round($aguinaldoProportional, 2),
                'pending_payroll'         => round($pendingPayroll, 2),
                'payroll_week'            => $payrollWeek,
                'total_settlement'        => round($totalSettlement, 2),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
            'role' => 'required|string|exists:roles,name',
            'job_position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'week_salary' => 'required|numeric|min:0',
            'birthdate' => 'required|date',
            'join_date' => 'required|date',
            // Validación para el horario de trabajo
            'work_schedule' => 'present|array|size:7',
            'work_schedule.*.works' => 'required|boolean',
            'work_schedule.*.start_time' => 'nullable|required_if:work_schedule.*.works,true',
            'work_schedule.*.end_time' => 'nullable|required_if:work_schedule.*.works,true|after:work_schedule.*.start_time',
            'work_schedule.*.break_minutes' => 'nullable|required_if:work_schedule.*.works,true|integer|min:0',
        ], [
            // Mensajes de error personalizados
            'work_schedule.*.start_time.required_if' => 'La hora de entrada es obligatoria para días laborales.',
            'work_schedule.*.end_time.required_if' => 'La hora de salida es obligatoria para días laborales.',
            'work_schedule.*.end_time.after' => 'La hora de salida debe ser posterior a la de entrada.',
            'work_schedule.*.break_minutes.required_if' => 'El tiempo de comida es obligatorio para días laborales.',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $user->assignRole($request->role);

            $totalHours = $this->calculateWeeklyHours($request->work_schedule ?? []);

            $employeeDetail = $user->employeeDetail()->create([
                'job_position' => $request->job_position,
                'department' => $request->department,
                'week_salary' => $request->week_salary,
                'birthdate' => $request->birthdate,
                'join_date' => $request->join_date,
                'work_days' => $request->work_schedule,
                'hours_per_week' => $totalHours,
            ]);

            $employeeDetail->bonuses()->sync($request->selected_bonuses);
            $employeeDetail->discounts()->sync($request->selected_discounts);
        });

        return redirect()->route('users.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        // Cargar las relaciones para pasarlas como props
        $user->load('roles', 'employeeDetail.bonuses', 'employeeDetail.discounts');

        return Inertia::render('User/Edit', [
            'user' => $user,
            'roles' => Role::all(),
            'bonuses' => Bonus::all(),
            'discounts' => Discount::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            // 'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', Rules\Password::defaults()],
            'role' => 'required|string|exists:roles,name',
            'job_position' => 'required|string|max:255',
            'department' => 'required|string|max:255',
            'week_salary' => 'required|numeric|min:0',
            'birthdate' => 'required|date',
            'join_date' => 'required|date',
            // Validación para el horario de trabajo
            'work_schedule' => 'present|array|size:7',
            'work_schedule.*.works' => 'required|boolean',
            'work_schedule.*.start_time' => 'nullable|required_if:work_schedule.*.works,true',
            // 'work_schedule.*.end_time' => 'nullable|required_if:work_schedule.*.works,true|after:work_schedule.*.start_time',
            'work_schedule.*.break_minutes' => 'nullable|required_if:work_schedule.*.works,true|integer|min:0',
        ], [
            // Mensajes de error personalizados
            'work_schedule.*.start_time.required_if' => 'La hora de entrada es obligatoria para días laborales.',
            // 'work_schedule.*.end_time.required_if' => 'La hora de salida es obligatoria para días laborales.',
            // 'work_schedule.*.end_time.after' => 'La hora de salida debe ser posterior a la de entrada.',
            'work_schedule.*.break_minutes.required_if' => 'El tiempo de comida es obligatorio para días laborales.',
        ]);

        DB::transaction(function () use ($request, $user) {
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            $user->syncRoles([$request->role]);

            $totalHours = $this->calculateWeeklyHours($request->work_schedule ?? []);

            $user->employeeDetail()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'job_position' => $request->job_position,
                    'department' => $request->department,
                    'week_salary' => $request->week_salary,
                    'birthdate' => $request->birthdate,
                    'join_date' => $request->join_date,
                    'work_days' => $request->work_schedule,
                    'hours_per_week' => $totalHours,
                ]
            );

            $user->employeeDetail->bonuses()->sync($request->selected_bonuses);
            $user->employeeDetail->discounts()->sync($request->selected_discounts);
        });

        return redirect()->route('users.index');
    }

    /**
     * Calculate total weekly hours from a work schedule array.
     */
    private function calculateWeeklyHours(array $workSchedule): float
    {
        $totalMinutes = 0;

        foreach ($workSchedule as $day) {
            if (isset($day['works']) && $day['works'] && !empty($day['start_time']) && !empty($day['end_time'])) {
                // Create Carbon instances from time strings for a consistent base date.
                $startTime = Carbon::createFromTimeString($day['start_time']);
                $endTime = Carbon::createFromTimeString($day['end_time']);

                // If end time is earlier than start time, it's an overnight shift.
                // We add a day to the end time to calculate the duration correctly across midnight.
                if ($endTime->lt($startTime)) {
                    $endTime->addDay();
                }

                // Calculate the difference directly from timestamps to avoid potential issues with diffInMinutes.
                // This ensures the duration is always a positive value.
                $diffInMinutes = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;

                $breakMinutes = isset($day['break_minutes']) ? (int)$day['break_minutes'] : 0;

                $totalMinutes += ($diffInMinutes - $breakMinutes);
            }
        }

        return round($totalMinutes / 60, 2); // Returns the total in hours with two decimals
    }

    /**
     * Build work-year periods based on the employee's join date and assign
     * vacation logs to their corresponding period.
     */
    private function buildWorkYears($joinDate, $vacationLogs): array
    {
        // Acepta tanto Carbon como string; normaliza a Carbon con fecha pura (sin hora)
        $joinDate = Carbon::parse($joinDate)->startOfDay();
        $now = Carbon::now()->startOfDay();

        $periodStart = $joinDate->copy();
        $yearNumber = 1;
        $workYears = [];

        while ($periodStart->lte($now)) {
            $periodEnd = $periodStart->copy()->addYear()->subDay()->startOfDay();
            if ($periodEnd->gt($now)) {
                $periodEnd = $now->copy();
            }

            $completedYearsAtStart = $yearNumber - 1;
            $maxDays = $this->getAnnualVacationDays($completedYearsAtStart);

            // Filter logs that fall within this work-year period
            $periodLogs = $vacationLogs->filter(function ($log) use ($periodStart, $periodEnd) {
                $logDate = Carbon::parse($log->date);
                return $logDate->between($periodStart, $periodEnd);
            })->values();

            // Accrued: positive contributions (earned, initial_balance, and positive manual adjustments)
            $accrued = $periodLogs->whereIn('type', ['earned', 'initial_balance'])->sum('days')
                + $periodLogs->where('type', 'manual_adjustment')->where('days', '>', 0)->sum('days');

            // Negative adjustments
            $negativeAdjustments = $periodLogs->where('type', 'manual_adjustment')->where('days', '<', 0)->sum('days');

            // Taken days (stored as negative values)
            $taken = abs($periodLogs->where('type', 'taken')->sum('days'));

            $workYears[] = [
                'year_number'        => $yearNumber,
                'start_date'         => $periodStart->toDateString(),
                'end_date'           => $periodEnd->toDateString(),
                'max_days_by_law'    => $maxDays,
                'accrued_days'       => round($accrued, 2),
                'negative_adjustments' => round(abs($negativeAdjustments), 2),
                'taken_days'         => round($taken, 2),
                'available_days'     => round($accrued + $negativeAdjustments - $taken, 2),
                'is_current'         => $periodEnd->isToday() || $periodEnd->isFuture(),
                'logs'               => $periodLogs,
            ];

            $periodStart = $periodEnd->copy()->addDay();
            $yearNumber++;
        }

        // Reverse: most recent period first
        return array_reverse($workYears);
    }

    /**
     * Devuelve el número de días de vacaciones anuales según los años de servicio cumplidos.
     * Basado en la Ley Federal del Trabajo de México (reforma "Vacaciones Dignas" 2023).
     */
    private function getAnnualVacationDays(int $completedYears): int
    {
        if ($completedYears >= 30) return 32;
        if ($completedYears >= 25) return 30;
        if ($completedYears >= 20) return 28;
        if ($completedYears >= 15) return 26;
        if ($completedYears >= 10) return 24;
        if ($completedYears >= 5) return 22;

        return 12 + (2 * $completedYears);
    }

    public function changeStatus(Request $request, User $user)
    {
        if ($user->is_active) {
            // Lógica para dar de baja
            $request->validate([
                'disabled_at' => 'required|date',
                'reason' => 'nullable|string|max:1000',
            ]);

            DB::transaction(function () use ($request, $user) {
                // Desvincular clientes
                Branch::where('account_manager_id', $user->id)->update(['account_manager_id' => null]);

                // Crear el log de baja
                TerminationLog::create([
                    'user_id' => $user->id,
                    'termination_date' => $request->disabled_at,
                    'reason' => $request->reason,
                    'terminated_by' => auth()->id(),
                ]);

                // Desactivar al usuario
                $user->update([
                    'is_active' => false,
                    'disabled_at' => $request->disabled_at,
                ]);
            });
        } else {
            // Lógica para reactivar
            DB::transaction(function () use ($user) {
                // Buscar el último log de baja que no haya sido reactivado
                $lastLog = TerminationLog::where('user_id', $user->id)
                    ->whereNull('reinstated_at')
                    ->latest('termination_date')
                    ->first();

                if ($lastLog) {
                    $lastLog->update(['reinstated_at' => now()]);
                }

                // Reactivar al usuario
                $user->update([
                    'is_active' => true,
                    'disabled_at' => null,
                ]);
            });
        }
    }

    public function getUnseenMessages()
    {
        $unseen_messages = ChMessage::where('to_id', auth()->id())->where('seen', 0)->get()->count();

        return response()->json(['count' => $unseen_messages]);
    }
}
