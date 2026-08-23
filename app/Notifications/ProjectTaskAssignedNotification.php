<?php

namespace App\Notifications;

use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectTaskAssignedNotification extends Notification
{
    use Queueable;

    protected $task;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProjectTask $task)
    {
        $this->task = $task;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Solo guardar en base de datos
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nueva tarea asignada',
            'folio' => 'TAREA-' . $this->task->id,
            'type' => 'project_task_assigned',
            // La URL lleva al proyecto con el modal de la tarea abierto
            'url' => route('projects.show', $this->task->project) . '?task=' . $this->task->id,
            'message' => "Se te ha asignado la tarea <strong>'{$this->task->title}'</strong> del proyecto <strong>'{$this->task->project->name}'</strong>.",
            'icon' => 'fa-solid fa-list-check',
        ];
    }
}
