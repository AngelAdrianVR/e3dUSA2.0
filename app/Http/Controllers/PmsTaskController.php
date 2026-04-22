<?php

namespace App\Http\Controllers;

use App\Models\PmsTask;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Notifications\PmsTaskAssignedNotification;

class PmsTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = PmsTask::with(['responsible', 'media']);

        // 1. Gestión de Permisos (Spatie)
        if (!$user->hasPermissionTo('Organizar tareas')) {
            // Si no es admin/gerente, solo ve las suyas
            $query->where('responsible_id', $user->id);
        }

        // 2. Filtro de expiradas
        if ($request->boolean('expired_only')) {
            $query->expired();
        }

        // 3. Filtro por departamento
        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        // 4. Filtro para ocultar tareas terminadas
        if ($request->boolean('hide_completed')) {
            $query->where('kanban_status', '!=', 'Terminado');
        }

        // 5. Paginación y ordenamiento (más próximas a vencer primero)
        $tasks = $query->orderBy('due_date', 'asc')->paginate(50);

        $users = User::where('is_active', true)->whereNot('id', 1)->get();

        return Inertia::render('PMS/Index', [
            'tasks' => $tasks,
            'filters' => $request->only(['department', 'expired_only', 'hide_completed', 'page']),
            'users' => $users,
            'canManage' => $user->hasPermissionTo('Organizar tareas'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'required|in:Producción,Ventas,Administración,Diseño,General',
            'priority' => 'required|in:Alta,Media,Baja',
            'due_date' => 'required|date',
            'responsible_id' => 'nullable|exists:users,id',
            'kanban_status' => 'required|in:Pendiente,En proceso,Validación,Terminado',
            'description' => 'nullable|string',
            'evidence_files.*' => 'nullable|file', // Permitir múltiples archivos
        ]);

        $validated['created_by'] = Auth::id();
        $validated['start_date'] = now();

        $task = PmsTask::create($validated);

        if ($request->hasFile('evidence_files')) {
            foreach ($request->file('evidence_files') as $file) {
                $task->addMedia($file)->toMediaCollection('evidence');
            }
        }

        // Notificar al responsable asignado (si no es quien creó la tarea)
        if ($task->responsible_id && $task->responsible_id !== Auth::id()) {
            $task->responsible->notify(new PmsTaskAssignedNotification($task));
        }

        return back()->with('success', 'Tarea creada y añadida al tablero.');
    }

    public function update(Request $request, PmsTask $pmsTask)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department' => 'required|in:Producción,Ventas,Administración,Diseño,General',
            'priority' => 'required|in:Alta,Media,Baja',
            'due_date' => 'required|date',
            'responsible_id' => 'nullable|exists:users,id',
            'kanban_status' => 'required|in:Pendiente,En proceso,Validación,Terminado',
            'description' => 'nullable|string',
            'evidence_files.*' => 'nullable|file', // Permitir múltiples archivos
        ]);

        // REGLAS DE PERMISOS
        if ($pmsTask->responsible_id !== $user->id && !$user->hasPermissionTo('Organizar tareas')) {
             return back()->withErrors(['permission' => 'No tienes permiso para modificar tareas de otras personas.']);
        }

        if ($pmsTask->kanban_status === 'Validación' && $validated['kanban_status'] === 'Terminado') {
            if (!$user->hasPermissionTo('Validar tareas')) {
                return back()->withErrors(['permission' => 'No tienes permiso para validar tareas (Requiere permiso "Validar tareas").']);
            }
        }

        // REGLA ISO
        if (in_array($validated['kanban_status'], ['Validación', 'Terminado']) && 
            !in_array($pmsTask->kanban_status, ['Validación', 'Terminado'])) {
            
            $hasEvidence = $pmsTask->getMedia('evidence')->isNotEmpty() || $request->hasFile('evidence_files');
            
            if (!$hasEvidence) {
                return back()->withErrors(['evidence_files' => 'No se puede avanzar la tarea sin cargar una evidencia (Regla ISO)']);
            }
        }

        if ($validated['kanban_status'] === 'Terminado' && $pmsTask->kanban_status !== 'Terminado') {
            $validated['finished_at'] = now();
        }

        $pmsTask->update($validated);

        // Notificar solo si se cambió al responsable durante la edición
        if ($pmsTask->wasChanged('responsible_id') && $pmsTask->responsible_id && $pmsTask->responsible_id !== Auth::id()) {
            $pmsTask->responsible->notify(new PmsTaskAssignedNotification($pmsTask));
        }

        // Guardar evidencias nuevas
        if ($request->hasFile('evidence_files')) {
            foreach ($request->file('evidence_files') as $file) {
                $pmsTask->addMedia($file)->toMediaCollection('evidence');
            }
        }

        if ($validated['kanban_status'] === 'Terminado' && $pmsTask->sourceable) {
            if (get_class($pmsTask->sourceable) === 'App\Models\ProductionTask') {
                $pmsTask->sourceable->update(['status' => 'Terminada', 'finished_at' => now()]);
            }
        }

        return back()->with('success', 'Tarea actualizada correctamente.');
    }

    public function destroy(PmsTask $pmsTask)
    {
        $pmsTask->delete();

        return back()->with('success', 'Tarea eliminada del tablero.');
    }

    public function updateStatus(Request $request, PmsTask $pmsTask)
    {
        $user = Auth::user();

        $request->validate([
            'kanban_status' => 'required|in:Pendiente,En proceso,Validación,Terminado',
            'responsible_id' => 'nullable|exists:users,id', // Permitir validar y actualizar esto desde Backlog
            'evidence_files.*' => 'nullable|file',
        ]);

        // REGLAS DE PERMISOS PARA KANBAN
        if ($pmsTask->responsible_id !== null && $pmsTask->responsible_id !== $user->id && !$user->hasPermissionTo('Organizar tareas')) {
             return back()->withErrors(['permission' => 'No tienes permiso para mover tareas de otras personas.']);
        }

        if ($pmsTask->kanban_status === 'Validación' && $request->kanban_status === 'Terminado') {
            if (!$user->hasPermissionTo('Validar tareas')) {
                return back()->withErrors(['permission' => 'No tienes permiso para validar tareas (Requiere permiso "Validar tareas").']);
            }
        }

        // REGLA ISO
        if (in_array($request->kanban_status, ['Validación', 'Terminado'])) {
            $hasEvidence = $pmsTask->getMedia('evidence')->isNotEmpty() || $request->hasFile('evidence_files');
            
            if (!$hasEvidence) {
                return back()->withErrors(['evidence_files' => 'No se puede avanzar la tarea sin cargar una evidencia (Regla ISO)']);
            }
        }

        if ($request->hasFile('evidence_files')) {
            foreach ($request->file('evidence_files') as $file) {
                $pmsTask->addMedia($file)->toMediaCollection('evidence');
            }
        }

        $dataToUpdate = [
            'kanban_status' => $request->kanban_status
        ];

        // Seteo explícito del responsable
        if ($request->has('responsible_id')) {
            $dataToUpdate['responsible_id'] = $request->responsible_id;
        }

        if ($request->kanban_status === 'Terminado' && $pmsTask->kanban_status !== 'Terminado') {
            $dataToUpdate['finished_at'] = now();
        }

        $pmsTask->update($dataToUpdate);

        // Notificar si se reasignó un responsable desde la vista Kanban/Backlog
        if ($pmsTask->wasChanged('responsible_id') && $pmsTask->responsible_id && $pmsTask->responsible_id !== Auth::id()) {
            $pmsTask->responsible->notify(new PmsTaskAssignedNotification($pmsTask));
        }

        if ($request->kanban_status === 'Terminado' && $pmsTask->sourceable) {
            if (get_class($pmsTask->sourceable) === 'App\Models\ProductionTask') {
                $pmsTask->sourceable->update(['status' => 'Terminada', 'finished_at' => now()]);
            }
        }

        return back()->with('success', 'Estatus actualizado');
    }
}