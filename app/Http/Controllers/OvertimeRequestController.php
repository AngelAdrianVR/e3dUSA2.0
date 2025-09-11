<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDetail;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Importar el trait

class OvertimeRequestController extends Controller
{
    use AuthorizesRequests; // Usar el trait para habilitar el método authorize()

    public function index()
    {
        $user = Auth::user();
        $query = OvertimeRequest::with('employeeDetail.user', 'approver');
        $employees = null;

        if ($user->can('Gestionar solicitudes de tiempo adicional')) {
            $employees = EmployeeDetail::whereHas('user', fn ($q) => $q->where('is_active', true))
                ->with('user:id,name')
                ->get(['id', 'user_id']);
        } else {
            $query->whereHas('employeeDetail', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        $requests = $query->latest()->paginate();

        return inertia('OvertimeRequest/Index', [
            'requests' => $requests,
            'employees' => $employees,
            'can' => [
                'manage_requests' => $user->can('Gestionar solicitudes de tiempo adicional'),
                'create_requests' => $user->can('Crear solicitudes de tiempo adicional'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'employee_detail_id' => $user->can('Gestionar solicitudes de tiempo adicional') ? 'required|exists:employee_details,id' : 'nullable',
            'date' => 'required|date',
            'requested_minutes' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        $employeeDetailId = null;
        if ($user->can('Gestionar solicitudes de tiempo adicional')) {
            $employeeDetailId = $validated['employee_detail_id'];
        } else {
            if (!$user->employeeDetail) {
                return back()->with('error', 'No tienes un perfil de empleado para crear solicitudes.');
            }
            $employeeDetailId = $user->employeeDetail->id;
        }

        OvertimeRequest::create([
            'employee_detail_id' => $employeeDetailId,
            'date' => $validated['date'],
            'requested_minutes' => $validated['requested_minutes'],
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Solicitud creada correctamente. Será revisada por un administrador.');
    }

    public function update(Request $request, OvertimeRequest $overtimeRequest)
    {
        // Ahora esta línea funcionará correctamente.
        $this->authorize('Gestionar solicitudes de tiempo adicional');

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $overtimeRequest->update([
            'status' => $validated['status'],
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Solicitud actualizada.');
    }
}

