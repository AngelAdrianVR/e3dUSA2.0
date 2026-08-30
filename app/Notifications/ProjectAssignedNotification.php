<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectAssignedNotification extends Notification
{
    use Queueable;

    protected $project;

    protected string $role;

    /**
     * Create a new notification instance.
     */
    public function __construct(Project $project, string $role = 'Colaborador')
    {
        $this->project = $project;
        $this->role = $role;
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
        $access = $this->role === 'Administrador' ? 'Administrador' : 'Colaborador';

        return [
            'title' => 'Nuevo proyecto asignado',
            'folio' => 'PROY-' . $this->project->id,
            'type' => 'project_assigned',
            'url' => route('projects.show', $this->project),
            'message' => "Has sido agregado al proyecto <strong>'{$this->project->name}'</strong> con acceso de {$access}.",
            'icon' => 'fa-solid fa-diagram-project',
        ];
    }
}
