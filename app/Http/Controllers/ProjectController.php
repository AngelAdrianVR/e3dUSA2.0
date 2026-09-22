<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\ProjectAssignedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProjectController extends Controller
{
    /**
     * Lista de proyectos. Si el usuario no tiene permiso global 'Ver proyectos',
     * solo ve los proyectos donde es miembro o creador.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Project::query()
            ->with([
                'members:id,name,email,profile_photo_path',
                'creator:id,name',
            ])
            ->withCount([
                'members',
                'tasks',
                'tasks as finished_tasks_count' => fn ($q) => $q->where('status', 'Terminada'),
            ]);

        // Control de visibilidad: solo el rol Super Administrador ve todos los proyectos;
        // el resto únicamente los proyectos donde participa (miembro o creador).
        if (!$user->hasRole('Super Administrador')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('members', fn ($m) => $m->whereKey($user->id))
                  ->orWhere('created_by', $user->id);
            });
        }

        // Búsqueda por nombre
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $projects = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        // Usuarios activos para asignar al crear/editar un proyecto
        $activeUsers = User::where('is_active', true)
            ->whereNot('id', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'profile_photo_path']);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'page']),
            'activeUsers' => $activeUsers,
            'canCreate' => $user->hasPermissionTo('Crear proyectos'),
        ]);
    }

    /**
     * Crea un proyecto (vía modal en el Index). El creador siempre queda como Administrador.
     */
    public function store(Request $request)
    {
        $validated = $this->validateProject($request);

        $validated['created_by'] = Auth::id();
        $validated['priority'] = $validated['priority'] ?? 'Normal';

        $project = Project::create($validated);

        $this->syncMembers($project, $request->input('members', []), true);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $project->addMedia($file)->toMediaCollection('files');
            }
        }

        return back()->with('success', 'Proyecto creado correctamente.');
    }

    /**
     * Actualiza un proyecto (vía modal en el Index).
     */
    public function update(Request $request, Project $project)
    {
        $this->authorizeEdit($project);

        $validated = $this->validateProject($request);

        $validated['priority'] = $validated['priority'] ?? 'Normal';

        $project->update($validated);

        $this->syncMembers($project, $request->input('members', []), false);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $project->addMedia($file)->toMediaCollection('files');
            }
        }

        return back()->with('success', 'Proyecto actualizado correctamente.');
    }

    /**
     * Detalle del proyecto con pestañas (Información, Tareas, Gantt).
     * Una sola petición con todos los datos (sin N+1).
     */
    public function show(Project $project)
    {
        $user = Auth::user();

        if (!$project->canView($user)) {
            abort(403, 'No tienes acceso a este proyecto.');
        }

        // Confirmación de que el miembro ya abrió el proyecto (primera visita)
        $project->markMemberViewed($user);

        // Solo los Administradores del proyecto ven el tiempo invertido y la evidencia
        $canSeeTaskTime = $project->isProjectAdmin($user);

        $project->load([
            'members:id,name,email,profile_photo_path',
            'creator:id,name',
            'tasks' => fn ($q) => $q->withCount(['media as evidence_count' => fn ($m) => $m->where('collection_name', 'evidence')])
                                       ->orderBy('position')->orderBy('id'),
            'tasks.assignee:id,name,email,profile_photo_path',
            'tasks.creator:id,name',
            'tasks.ratedBy:id,name',
            // La evidencia de finalización no se envía a los Colaboradores
            'tasks.media' => fn ($q) => $canSeeTaskTime ? $q : $q->where('collection_name', '!=', 'evidence'),
            'tasks.comments' => fn ($q) => $q->orderBy('created_at'),
            'tasks.comments.author:id,name,email,profile_photo_path',
            'media',
        ]);

        if ($canSeeTaskTime) {
            // Solo los Administradores del proyecto ven el tiempo invertido y las notas de finalización
            $project->tasks->each->append(['is_timer_running', 'total_time_seconds']);
        } else {
            $project->tasks->each->makeHidden(['time_spent_seconds', 'timer_started_at', 'completion_notes']);
        }

        $project->loadCount([
            'tasks',
            'tasks as pending_tasks_count' => fn ($q) => $q->where('status', 'Pendiente'),
            'tasks as in_progress_tasks_count' => fn ($q) => $q->where('status', 'En proceso'),
            'tasks as paused_tasks_count' => fn ($q) => $q->where('status', 'Pausada'),
            'tasks as finished_tasks_count' => fn ($q) => $q->where('status', 'Terminada'),
        ]);

        $activeUsers = User::where('is_active', true)
            ->whereNot('id', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'profile_photo_path']);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'canEdit' => $project->canEdit($user),
            'canDelete' => $user->hasPermissionTo('Eliminar proyectos') || $project->isCreator($user),
            'memberRole' => $project->memberRole($user),
            'isMember' => $project->isMember($user) || $project->isCreator($user),
            // Ver tiempo invertido y evidencia de tareas (Administrador del proyecto)
            'canSeeTaskTime' => $canSeeTaskTime,
            'activeUsers' => $activeUsers,
        ]);
    }

    /**
     * Carga de tareas por usuario (para evaluar saturación) en los proyectos visibles.
     * Devuelve JSON para pintarlo en un drawer sin recargar la página.
     */
    public function workload()
    {
        $user = Auth::user();

        // Mismos proyectos que puede ver en el índice
        $projectQuery = Project::query();
        if (!$user->hasRole('Super Administrador')) {
            $projectQuery->where(function ($q) use ($user) {
                $q->whereHas('members', fn ($m) => $m->whereKey($user->id))
                  ->orWhere('created_by', $user->id);
            });
        }

        $projectIds = $projectQuery->pluck('id');

        $tasks = ProjectTask::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('assigned_to')
            ->with('assignee:id,name,profile_photo_path')
            ->get(['id', 'project_id', 'assigned_to', 'status', 'due_date', 'rating']);

        $today = now()->startOfDay();
        $soonLimit = now()->addDays(3)->endOfDay();

        $users = $tasks->groupBy('assigned_to')->map(function ($userTasks) use ($today, $soonLimit) {
            $assignee = $userTasks->first()->assignee;

            $pending = $userTasks->where('status', 'Pendiente')->count();
            $inProgress = $userTasks->where('status', 'En proceso')->count();
            $paused = $userTasks->where('status', 'Pausada')->count();
            $finished = $userTasks->where('status', 'Terminada')->count();

            $open = $userTasks->where('status', '!=', 'Terminada');

            $overdue = $open->filter(fn ($task) => $task->due_date && $task->due_date->lt($today))->count();
            $dueSoon = $open->filter(fn ($task) => $task->due_date && $task->due_date->gte($today) && $task->due_date->lte($soonLimit))->count();

            $rated = $userTasks->whereNotNull('rating');

            return [
                'user_id' => $assignee?->id,
                'name' => $assignee?->name ?? 'Usuario eliminado',
                'profile_photo_url' => $assignee?->profile_photo_url,
                'pending' => $pending,
                'in_progress' => $inProgress,
                'paused' => $paused,
                'finished' => $finished,
                'active' => $pending + $inProgress + $paused,
                'overdue' => $overdue,
                'due_soon' => $dueSoon,
                'rating_average' => $rated->count() ? round((float) $rated->avg('rating'), 1) : null,
                'rating_count' => $rated->count(),
            ];
        })->values()->sortByDesc('active')->values();

        return response()->json(['users' => $users]);
    }

    /**
     * Sube archivos/imágenes a un proyecto existente (endpoint dedicado,
     * evita re-validar todo el formulario de edición).
     */
    public function uploadFiles(Request $request, Project $project)
    {
        $this->authorizeEdit($project);

        $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|max:10240',
        ]);

        foreach ($request->file('files') as $file) {
            $project->addMedia($file)->toMediaCollection('files');
        }

        return back()->with('success', 'Archivos subidos correctamente.');
    }

    /**
     * Elimina un proyecto.
     */
    public function destroy(Project $project)
    {
        $user = Auth::user();

        if (!$user->hasPermissionTo('Eliminar proyectos') && !$project->isCreator($user)) {
            return back()->withErrors(['permission' => 'No tienes permiso para eliminar este proyecto.']);
        }

        $project->delete();

        // Redirige siempre al índice: si borras desde el Show, back() apuntaría
        // a la URL del proyecto ya eliminado y daría 404.
        return redirect()->route('projects.index')->with('success', 'Proyecto eliminado.');
    }

    // ===================== Helpers privados =====================

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:Normal,Urgente',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'required|in:MXN,USD',
            'start_date' => 'required|date',
            'tentative_end_date' => 'nullable|date|after_or_equal:start_date',
            'members' => 'nullable|array',
            'members.*.user_id' => 'required|exists:users,id',
            'members.*.role' => 'required|in:Colaborador,Administrador',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240',
        ]);
    }

    /**
     * Sincroniza los miembros del proyecto. El creador siempre queda como Administrador.
     */
    private function syncMembers(Project $project, array $members, bool $isNew): void
    {
        $creatorId = (int) $project->created_by;

        // Mapa user_id => role (excluyendo al creador, que se re-agrega al final como Administrador)
        $memberData = collect($members)
            ->reject(fn ($m) => (int) $m['user_id'] === $creatorId)
            ->mapWithKeys(fn ($m) => [(int) $m['user_id'] => ['role' => $m['role']]])
            ->toArray();

        $memberData[$creatorId] = ['role' => 'Administrador'];

        // Detectar miembros nuevos para notificar
        $currentMemberIds = $project->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $newMemberIds = $isNew
            ? array_keys($memberData)
            : array_diff(array_keys($memberData), $currentMemberIds);

        $project->members()->sync($memberData);

        // Notificar a los miembros nuevos (excepto el creador)
        $currentUserId = (int) Auth::id();
        foreach ($newMemberIds as $userId) {
            if ((int) $userId === $creatorId || (int) $userId === $currentUserId) {
                continue;
            }

            $user = User::find($userId);
            if ($user) {
                $user->notify(new ProjectAssignedNotification($project, $memberData[$userId]['role']));
            }
        }
    }

    private function authorizeEdit(Project $project): void
    {
        if (!$project->canEdit(Auth::user())) {
            abort(403, 'No tienes permiso de escritura en este proyecto.');
        }
    }
}
