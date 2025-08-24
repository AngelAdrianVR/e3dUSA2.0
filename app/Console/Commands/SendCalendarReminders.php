<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CalendarEntry;
use App\Notifications\CalendarReminderNotification;
use Carbon\Carbon;
// Asegúrate de que los namespaces de tus modelos Task y Event sean correctos.
use App\Models\Task;
use App\Models\Event;

class SendCalendarReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-calendar-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía recordatorios para eventos/tareas activos que son hoy o mañana.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Buscando eventos/tareas para hoy y mañana...');

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        // Paso 1: Obtener todas las entradas de hoy y mañana, cargando la relación polimórfica.
        // Eager loading con with('entryable') previene problemas de N+1 queries.
        $potentialEntries = CalendarEntry::with('entryable')
            ->where(function ($query) use ($today, $tomorrow) {
                $query->whereDate('start_datetime', $today->toDateString())
                      ->orWhereDate('start_datetime', $tomorrow->toDateString());
            })
            ->get();

        // Paso 2: Filtrar las entradas según el estado de la tarea o del participante del evento.
        $entries = $potentialEntries->filter(function ($entry) {
            // Si el objeto relacionado (Task o Event) no existe, lo descartamos.
            if (!$entry->entryable) {
                return false;
            }

            // Si es una Tarea (Task), verificamos que su estado sea 'Pendiente'.
            if ($entry->entryable instanceof Task) {
                return $entry->entryable->status === 'Pendiente';
            }

            // Si es un Evento (Event), verificamos que el propietario de la entrada del calendario
            // sea un participante con estado 'Aceptado'.
            if ($entry->entryable instanceof Event) {
                // Buscamos al participante específico (el dueño de la entrada del calendario)
                $participant = $entry->entryable->participants()
                                  ->where('users.id', $entry->user_id) // Especificamos la tabla 'users' para claridad
                                  ->first();

                // ¡CORRECCIÓN AQUÍ! Se accede al estado a través del objeto 'pivot'.
                return $participant && $participant->pivot->status === 'Aceptado';
            }

            // Si es otro tipo de entrada, no se envía recordatorio.
            return false;
        });


        if ($entries->isEmpty()) {
            $this->info('No se encontraron eventos o tareas que cumplan los criterios para hoy o mañana.');
            return 0;
        }

        $this->info("Se encontraron {$entries->count()} eventos/tareas que cumplen los criterios.");

        foreach ($entries as $entry) {
            $user = $entry->owner;

            if ($user) {
                $isToday = Carbon::parse($entry->start_datetime)->isToday();
                $reminderType = $isToday ? 'today' : 'tomorrow';
                $when = $isToday ? 'hoy' : 'mañana';

                try {
                    $user->notify(new CalendarReminderNotification($entry, $reminderType));
                    $this->info("Notificación enviada a {$user->name} para '{$entry->title}' (evento es {$when}).");
                } catch (\Exception $e) {
                    $this->error("Error al notificar para el evento ID {$entry->id}: " . $e->getMessage());
                }
            } else {
                $this->warn("No se encontró propietario para el evento ID {$entry->id}.");
            }
        }

        $this->info('Proceso de envío de recordatorios finalizado.');
        return 0;
    }
}
