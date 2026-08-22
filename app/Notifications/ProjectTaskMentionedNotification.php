<?php

namespace App\Notifications;

use App\Models\ProjectTaskComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectTaskMentionedNotification extends Notification
{
    use Queueable;

    protected $comment;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProjectTaskComment $comment)
    {
        $this->comment = $comment;
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
        $task = $this->comment->task;
        $authorName = $this->comment->author->name ?? 'Alguien';

        return [
            'title' => 'Te mencionaron en un comentario',
            'folio' => 'TAREA-' . $task->id,
            'type' => 'project_task_mentioned',
            // La URL lleva al proyecto con el modal de la tarea abierto
            'url' => route('projects.show', $task->project) . '?task=' . $task->id,
            'message' => "{$authorName} te mencionó en la tarea <strong>'{$task->title}'</strong> del proyecto <strong>'{$task->project->name}'</strong>.",
            'icon' => 'fa-solid fa-at',
        ];
    }
}
