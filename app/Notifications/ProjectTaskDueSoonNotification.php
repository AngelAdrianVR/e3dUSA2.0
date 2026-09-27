<?php

namespace App\Notifications;

use App\Models\ProjectTask;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifica al responsable de una tarea de proyecto que está a 3 días o menos
 * de vencer, o que ya venció. Se envía diariamente (canal database) mientras
 * la tarea siga sin estatus "Terminada".
 */
class ProjectTaskDueSoonNotification extends Notification
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
        $dueDate = $this->task->due_date ? Carbon::parse($this->task->due_date) : null;

        // Días restantes: negativo = ya venció, 0 = vence hoy, positivo = por vencer
        $daysLeft = $dueDate ? (int) Carbon::today()->diffInDays($dueDate, false) : null;
        $isOverdue = $daysLeft !== null && $daysLeft < 0;

        if ($isOverdue) {
            $daysOverdue = abs($daysLeft);
            $statusText = $daysOverdue === 1 ? 'venció ayer' : "venció hace {$daysOverdue} días";
        } elseif ($daysLeft === 0) {
            $statusText = 'vence hoy';
        } elseif ($daysLeft === 1) {
            $statusText = 'vence mañana';
        } else {
            $statusText = "vence en {$daysLeft} días";
        }

        return [
            'title' => $isOverdue ? 'Tarea vencida' : 'Tarea por vencer',
            'task_id' => $this->task->id,
            'folio' => 'TAREA-' . $this->task->id,
            'type' => 'project_task_due_soon',
            // Lleva al proyecto en la pestaña de tareas y abre el modal de la tarea
            // Ruta RELATIVA: el comando corre por cron (sin request), así el enlace funciona
            // en cualquier host aunque APP_URL no coincida con el dominio real.
            'url' => route('projects.show', $this->task->project_id, false) . '?tab=tasks&task=' . $this->task->id,
            'message' => "La tarea <strong>'{$this->task->title}'</strong> del proyecto <strong>'{$this->task->project->name}'</strong> {$statusText}.",
            'icon' => $isOverdue ? 'fa-solid fa-triangle-exclamation' : 'fa-solid fa-hourglass-half',
            'due_date' => $dueDate?->toDateString(),
            'days_left' => $daysLeft,
        ];
    }
}
