<?php

namespace App\Http\Middleware;

use App\Models\Attendance;
use App\Models\AuthorizedDevice;
use App\Models\Payroll;
use App\Models\Quote;
use App\Models\Release; // Importante: Importar el modelo Release
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $attendanceStatus = null;
        $payrollPeriods = collect();
        $popupRelease = null;

        // Si el usuario está autenticado, cargamos sus detalles de empleado y lógica de releases.
        if ($user) {
            $user->load(['employeeDetail', 'roles']);

            // --- LÓGICA DE RELEASES / ACTUALIZACIONES ---
            // Solo usuarios activos ven releases; los inactivos no.
            if ($user->is_active) {
                // Buscamos la release publicada más antigua que el usuario NO haya visto
                // y que esté dirigida a él (target_all = true O está en release_targets)
                $popupRelease = Release::where('is_published', true)
                    ->where(function ($query) use ($user) {
                        $query->where('target_all', true)
                            ->orWhereHas('targetUsers', function ($q) use ($user) {
                                $q->where('user_id', $user->id);
                            });
                    })
                    ->whereDoesntHave('users', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->with(['items' => function ($query) {
                        $query->orderBy('order');
                    }, 'items.media'])
                    ->orderBy('published_at', 'asc')
                    ->first();
            }
            // ---------------------------------------------

            // Si tiene un perfil de empleado, procedemos a buscar sus datos de asistencia.
            if ($user->employeeDetail) {
                $employeeDetailId = $user->employeeDetail->id;

                // 1. Obtener el estado de asistencia actual del día.
                $latestAttendance = Attendance::where('employee_detail_id', $employeeDetailId)
                    ->whereDate('timestamp', Carbon::today())
                    ->latest('timestamp')
                    ->first();

                $status = 'not_clocked_in';
                $timestamp = null;

                if ($latestAttendance) {
                    switch ($latestAttendance->type) {
                        case 'entry':
                        case 'end_break':
                            $status = 'working';
                            break;
                        case 'start_break':
                            $status = 'on_break';
                            break;
                        case 'exit':
                            $status = 'clocked_out';
                            break;
                    }
                    $timestamp = $latestAttendance->timestamp;
                }

                $attendanceStatus = ['status' => $status, 'timestamp' => $timestamp];

                // 2. Obtener todos los periodos de nómina disponibles.
                $payrollPeriods = Payroll::orderBy('start_date', 'desc')->get();
            }
        }

        // --- NOTIFICACIONES CRM: Cotizaciones autorizadas sin respuesta del cliente ---
        $pendingQuoteNotifications = 0;
        $pendingSaleNotifications = 0;
        if ($user) {
            $pendingQuoteNotifications = Quote::where('user_id', $user->id)
                ->where('is_active', true)
                ->whereNotNull('authorized_at')
                ->whereNull('customer_responded_at')
                ->count();

            $pendingSaleNotifications = Sale::where('user_id', $user->id)
                ->where('status', 'Autorizada')
                ->count();
        }
        // -------------------------------------------------------------------------

        $isAuthorizedDevice = false;
        $deviceToken = $request->cookie('attendance_device_token');
        if ($deviceToken) {
            $isAuthorizedDevice = AuthorizedDevice::where('identifier', $deviceToken)
                ->where('is_active', true)
                ->exists();
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'profile_photo_url' => $user->profile_photo_url,
                    'notifications' => $user->notifications()->latest()->take(30)->get(),
                    'active_alerts' => $user->active_alerts ?? [],
                    'role' => $user->roles->first()?->name,
                    // Incluimos los detalles del empleado para tenerlos a mano.
                    'employee_detail' => $user->employeeDetail,
                ] : null,
            ],
            // Añadimos los nuevos datos como props globales.
            'attendance_status' => $attendanceStatus,
            'payroll_periods' => $payrollPeriods,
            'is_authorized_device' => $isAuthorizedDevice,
            'popupRelease' => $popupRelease, // <--- AQUÍ PASAMOS LA VARIABLE AL FRONTEND
            'pending_quote_notifications' => $pendingQuoteNotifications,
            'pending_sale_notifications' => $pendingSaleNotifications,
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'warning' => $request->session()->get('warning'),
                    'info' => $request->session()->get('info'),
                    'token' => $request->session()->get('token'),
                ];
            },
        ]);
    }
}