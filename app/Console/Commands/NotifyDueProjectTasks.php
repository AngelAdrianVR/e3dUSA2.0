<?php

namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Notifications\ProjectTaskDueSoonNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Revisa las tareas de proyectos que están a 3 días o menos de vencer
 * (incluye las ya vencidas) y notifica diariamente a su responsable.
 * Si la tarea no tiene responsable, se notifica al creador del proyecto.
 */
class NotifyDueProjectTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-due-project-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica a los responsables de tareas de proyectos que están a 3 días o menos de vencer (incluye vencidas).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $dueLimit = $today->copy()->addDays(3);

        $tasks = ProjectTask::with(['project.creator', 'assignee'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $dueLimit->toDateString())
            ->where('status', '!=', 'Terminada')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No hay tareas próximas a vencer ni vencidas pendientes.');
            return self::SUCCESS;
        }

        // Notificaciones de este tipo ya enviadas hoy (evita duplicados si el comando corre más de una vez)
        $sentToday = DB::table('notifications')
            ->where('type', ProjectTaskDueSoonNotification::class)
            ->where('created_at', '>=', $today->copy()->startOfDay())
            ->get(['notifiable_id', 'data'])
            ->mapWithKeys(function ($notification) {
                $data = json_decode($notification->data, true);
                return [$notification->notifiable_id . ':' . ($data['task_id'] ?? 0) => true];
            });

        $sentCount = 0;

        foreach ($tasks as $task) {
            // Responsable de la tarea; si no tiene asignado, se avisa al creador del proyecto.
            $user = $task->assignee ?? $task->project?->creator;

            if (!$user) {
                continue;
            }

            $key = $user->id . ':' . $task->id;

            if (isset($sentToday[$key])) {
                continue;
            }

            $user->notify(new ProjectTaskDueSoonNotification($task));
            $sentToday[$key] = true;
            $sentCount++;
        }

        $message = "Notificaciones de tareas por vencer enviadas: {$sentCount}";
        $this->info($message);
        Log::info($message);

        return self::SUCCESS;
    }
}
