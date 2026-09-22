<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskComment;
use App\Models\User;
use App\Notifications\ProjectTaskAssignedNotification;
use App\Notifications\ProjectTaskMentionedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectTaskController extends Controller
{
    /**
     * Crea una tarea dentro de un proyecto.
     */
    public function store(Request $request, Project $project)
    {
        $this->authorizeEdit($project);

        $validated = $this->validateTask($request);

        if (!empty($validated['assigned_to']) && !$this->isMember($project, $validated['assigned_to'])) {
            return back()->withErrors(['assigned_to' => 'Solo puedes asignar la tarea a miembros del proyecto.']);
        }

        $validated['project_id'] = $project->id;
        $validated['created_by'] = Auth::id();
        $validated['position'] = $project->tasks()->count();

        if ($validated['status'] === 'Terminada') {
            $validated['finished_at'] = now();
        }

        $task = ProjectTask::create($validated);

        // Si la tarea nace en proceso, arranca el cronómetro
        if ($task->status === 'En proceso') {
            $task->startTimer();
            $task->save();
        }

        $this->saveFiles($task, $request);

        $this->notifyAssigneeIfChanged($task, null);

        return back()->with('success', 'Tarea creada correctamente.');
    }

    /**
     * Actualiza una tarea (los archivos se envían por POST + _method=put).
     */
    public function update(Request $request, Project $project, ProjectTask $task)
    {
        $this->authorizeEdit($project);
        abort_if($task->project_id !== $project->id, 404);

        $validated = $this->validateTask($request);

        if (!empty($validated['assigned_to']) && !$this->isMember($project, $validated['assigned_to'])) {
            return back()->withErrors(['assigned_to' => 'Solo puedes asignar la tarea a miembros del proyecto.']);
        }

        $oldAssignee = $task->assigned_to;
        $oldStatus = $task->status;

        // No se puede finalizar una tarea sin evidencia
        if (($validated['status'] ?? $oldStatus) === 'Terminada' && $oldStatus !== 'Terminada'
            && !$request->hasFile('evidence') && !$task->hasEvidence()) {
            return back()->withErrors(['status' => 'Para finalizar la tarea debes adjuntar al menos una evidencia (foto, documento o video).']);
        }

        $this->applyStatusDates($validated, $oldStatus);
        $this->applyTimerState($validated, $task, $oldStatus);

        $task->update($validated);

        $this->saveFiles($task, $request);
        $this->saveEvidence($task, $request);

        $this->notifyAssigneeIfChanged($task, $oldAssignee);

        return back()->with('success', 'Tarea actualizada correctamente.');
    }

    /**
     * Elimina una tarea.
     */
    public function destroy(Project $project, ProjectTask $task)
    {
        $this->authorizeEdit($project);
        abort_if($task->project_id !== $project->id, 404);

        $task->delete();

        return back()->with('success', 'Tarea eliminada.');
    }

    /**
     * Actualiza el estatus desde el Kanban (drag & drop).
     */
    public function updateStatus(Request $request, Project $project, ProjectTask $task)
    {
        $user = Auth::user();
        abort_if($task->project_id !== $project->id, 404);

        // Permiso para cambiar estatus (drag & drop):
        // - Administrador (Administrador/creador/permiso global): puede mover cualquier tarea.
        // - Colaborador: únicamente sus propias tareas y sin reasignar.
        if (!$project->canEdit($user)) {
            if ((int) $task->assigned_to !== (int) $user->id) {
                abort(403, 'Solo puedes mover tus propias tareas.');
            }
            if ($request->has('assigned_to')) {
                abort(403, 'No tienes permiso para reasignar la tarea.');
            }
        }

        $request->validate(array_merge([
            'status' => 'required|in:Pendiente,En proceso,Pausada,Terminada',
            'position' => 'nullable|integer|min:0',
            'assigned_to' => 'nullable|exists:users,id',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240',
        ], $this->evidenceRules()));

        $data = ['status' => $request->status];

        if ($request->has('position')) {
            $data['position'] = (int) $request->position;
        }

        if ($request->has('assigned_to')) {
            if (!empty($request->assigned_to) && !$this->isMember($project, $request->assigned_to)) {
                return back()->withErrors(['assigned_to' => 'Solo puedes asignar la tarea a miembros del proyecto.']);
            }
            $data['assigned_to'] = $request->assigned_to;
        }

        $oldStatus = $task->status;
        $oldAssignee = $task->assigned_to;

        // No se puede finalizar una tarea sin evidencia
        if ($request->status === 'Terminada' && $oldStatus !== 'Terminada'
            && !$request->hasFile('evidence') && !$task->hasEvidence()) {
            return back()->withErrors(['status' => 'Para finalizar la tarea debes adjuntar al menos una evidencia (foto, documento o video).']);
        }

        $this->applyStatusDates($data, $oldStatus);
        $this->applyTimerState($data, $task, $oldStatus);

        $task->update($data);

        // Subida de archivos/evidencia desde el Kanban o el Dashboard
        $this->saveFiles($task, $request);
        $this->saveEvidence($task, $request);

        $this->notifyAssigneeIfChanged($task, $oldAssignee);

        return back()->with('success', 'Estatus actualizado.');
    }

    /**
     * Finaliza una tarea adjuntando la evidencia obligatoria (máximo 3 archivos)
     * y notas opcionales. La evidencia solo la ven los Administradores del proyecto.
     */
    public function finish(Request $request, Project $project, ProjectTask $task)
    {
        $user = Auth::user();
        abort_if($task->project_id !== $project->id, 404);

        // Puede finalizar: Administrador del proyecto o el responsable de la tarea
        if (!$project->canEdit($user) && (int) $task->assigned_to !== (int) $user->id) {
            abort(403, 'No puedes finalizar esta tarea.');
        }

        $validated = $request->validate([
            'files' => 'required|array|min:1|max:3',
            'files.*' => 'file|mimes:jpg,jpeg,png,gif,webp,heic,mp4,mov,avi,mkv,wmv,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip|max:20480',
            'completion_notes' => 'nullable|string|max:2000',
        ]);

        if ($task->getMedia('evidence')->count() + count($request->file('files')) > 3) {
            return back()->withErrors(['files' => 'Puedes adjuntar máximo 3 evidencias por tarea.']);
        }

        foreach ($request->file('files') as $file) {
            $task->addMedia($file)->toMediaCollection('evidence');
        }

        // Detiene el cronómetro y marca la tarea como terminada
        $task->stopTimer();
        $task->completion_notes = $validated['completion_notes'] ?? null;
        $task->status = 'Terminada';
        $task->finished_at = now();
        $task->save();

        return back()->with('success', 'Tarea finalizada con evidencia.');
    }

    /**
     * Califica el desempeño del responsable de la tarea (tiempo, resultados y eficiencia).
     * Solo puede calificar quien creó el proyecto; puede editar su calificación.
     */
    public function rateTask(Request $request, Project $project, ProjectTask $task)
    {
        $user = Auth::user();
        abort_if($task->project_id !== $project->id, 404);

        if (!$project->isCreator($user)) {
            abort(403, 'Solo quien creó el proyecto puede calificar el desempeño.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'rating_note' => 'nullable|string|max:1000',
        ]);

        $task->update([
            'rating' => $validated['rating'],
            'rating_note' => $validated['rating_note'] ?? null,
            'rated_by' => $user->id,
            'rated_at' => now(),
        ]);

        return back()->with('success', 'Calificación guardada.');
    }

    /**
     * Agrega un comentario a una tarea (cualquier miembro, incluso Colaborador).
     * Devuelve JSON para insertarlo sin recargar la página.
     */
    public function storeComment(Request $request, Project $project, ProjectTask $task)
    {
        $user = Auth::user();

        if (!$project->canView($user)) {
            abort(403, 'No tienes acceso a este proyecto.');
        }
        abort_if($task->project_id !== $project->id, 404);

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'mentioned_user_ids' => 'nullable|array',
            'mentioned_user_ids.*' => 'integer|exists:users,id',
        ]);

        // Menciones válidas: miembros del proyecto y no el autor (quien recibe la notificación)
        $mentionedIds = collect($validated['mentioned_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $user->id)
            ->filter(fn ($id) => $this->isMember($project, $id))
            ->unique()
            ->values();

        $comment = ProjectTaskComment::create([
            'project_task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
            'mentioned_user_ids' => $mentionedIds->all(),
            'read_by' => [],
        ]);

        if ($mentionedIds->isNotEmpty()) {
            $comment->load(['task.project', 'author']);
            $task->load('project');

            User::whereIn('id', $mentionedIds)->get()->each(function ($member) use ($comment) {
                $member->notify(new ProjectTaskMentionedNotification($comment));
            });
        }

        $comment->load('author:id,name,email,profile_photo_path');

        return response()->json([
            'comment' => $comment,
            'message' => 'Comentario agregado.',
        ], 201);
    }

    /**
     * Marca como leídos los comentarios de la tarea donde el usuario actual fue mencionado.
     * Devuelve JSON con los comentarios actualizados (sin recargar la página).
     */
    public function markCommentsRead(Request $request, Project $project, ProjectTask $task)
    {
        $user = Auth::user();

        if (!$project->canView($user)) {
            abort(403, 'No tienes acceso a este proyecto.');
        }
        abort_if($task->project_id !== $project->id, 404);

        $now = now()->toIso8601String();

        $task->comments()
            ->whereJsonContains('mentioned_user_ids', (int) $user->id)
            ->get()
            ->each(function ($comment) use ($user, $now) {
                $readBy = collect($comment->read_by ?? []);
                $alreadyRead = $readBy->contains(fn ($r) => (int) ($r['user_id'] ?? 0) === (int) $user->id);

                if (!$alreadyRead) {
                    $readBy->push(['user_id' => (int) $user->id, 'read_at' => $now]);
                    $comment->update(['read_by' => $readBy->values()->all()]);
                }
            });

        $comments = $task->comments()
            ->with('author:id,name,email,profile_photo_path')
            ->orderBy('created_at')
            ->get();

        return response()->json(['comments' => $comments]);
    }

    // ===================== Helpers privados =====================

    private function validateTask(Request $request): array
    {
        return $request->validate(array_merge([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:Pendiente,En proceso,Pausada,Terminada',
            'assigned_to' => 'nullable|exists:users,id',
            'files' => 'nullable|array|max:10',
            'files.*' => 'file|max:10240',
        ], $this->evidenceRules()));
    }

    /**
     * Reglas de la evidencia de finalización (fotos, documentos y videos).
     */
    private function evidenceRules(): array
    {
        return [
            'evidence' => 'nullable|array|max:3',
            'evidence.*' => 'file|mimes:jpg,jpeg,png,gif,webp,heic,mp4,mov,avi,mkv,wmv,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip|max:20480',
            'completion_notes' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Guarda la evidencia enviada (máximo 3 por tarea).
     */
    private function saveEvidence(ProjectTask $task, Request $request): void
    {
        if (!$request->hasFile('evidence')) {
            return;
        }

        $available = max(0, 3 - $task->getMedia('evidence')->count());

        foreach (array_slice($request->file('evidence'), 0, $available) as $file) {
            $task->addMedia($file)->toMediaCollection('evidence');
        }
    }

    /**
     * Controla el cronómetro del tiempo invertido:
     * arranca al pasar a 'En proceso' y se pausa al salir de él (Pendiente, Pausada, Terminada).
     */
    private function applyTimerState(array &$data, ProjectTask $task, ?string $oldStatus): void
    {
        $newStatus = $data['status'] ?? $oldStatus;

        if ($newStatus === $oldStatus) {
            return;
        }

        if ($newStatus === 'En proceso') {
            $data['timer_started_at'] = $task->timer_started_at ?? now();

            return;
        }

        if ($task->timer_started_at !== null) {
            // diffInSeconds con $absolute = true (Carbon 3 devuelve valores firmados)
            $data['time_spent_seconds'] = (int) $task->time_spent_seconds
                + max(0, (int) $task->timer_started_at->diffInSeconds(now(), true));
            $data['timer_started_at'] = null;
        }
    }

    /**
     * Al pasar a 'Terminada' guarda la fecha real; si sale de 'Terminada' la limpia.
     */
    private function applyStatusDates(array &$data, ?string $oldStatus): void
    {
        if (($data['status'] ?? null) === 'Terminada' && $oldStatus !== 'Terminada') {
            $data['finished_at'] = now();
        } elseif (($data['status'] ?? null) !== 'Terminada' && $oldStatus === 'Terminada') {
            $data['finished_at'] = null;
        }
    }

    private function saveFiles(ProjectTask $task, Request $request): void
    {
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $task->addMedia($file)->toMediaCollection('files');
            }
        }
    }

    private function isMember(Project $project, int $userId): bool
    {
        return $project->members()->whereKey($userId)->exists()
            || (int) $project->created_by === (int) $userId;
    }

    private function notifyAssigneeIfChanged(ProjectTask $task, ?int $oldAssignee): void
    {
        $currentUserId = (int) Auth::id();

        if ($task->assigned_to && (int) $task->assigned_to !== $currentUserId && (int) $task->assigned_to !== (int) $oldAssignee) {
            $task->load('project');
            $task->assignee->notify(new ProjectTaskAssignedNotification($task));
        }
    }

    private function authorizeEdit(Project $project): void
    {
        if (!$project->canEdit(Auth::user())) {
            abort(403, 'No tienes permiso de escritura en este proyecto.');
        }
    }
}
