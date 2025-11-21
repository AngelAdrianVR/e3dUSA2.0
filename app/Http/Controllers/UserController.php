<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\Branch;
use App\Models\ChMessage;
use App\Models\Discount;
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
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
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
