<?php

namespace App\Notifications;

use App\Models\PmsTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PmsTaskAssignedNotification extends Notification
{
    use Queueable;

    protected $task;

    /**
     * Create a new notification instance.
     */
    public function __construct(PmsTask $task)
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
        // Esta es la info que el componente Vue leerá
        return [
            'title' => 'Nueva tarea asignada',
            'folio' => 'PMS-' . $this->task->id,
            'type' => 'pms_task_assigned',
            // Asumo que la ruta de tu kanban se llama 'pms.index' (basado en el controlador)
            'url' => route('pms.index'), 
            'message' => "Se te ha asignado la tarea <strong>'{$this->task->title}'</strong> en el departamento de {$this->task->department}.",
            'icon' => 'fa-solid fa-list-check', 
        ];
    }
}