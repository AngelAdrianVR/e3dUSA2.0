<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Notifications\EventInvitationNotification;
use App\Notifications\EventResponseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarController extends Controller
{
    public function index()
    {
        $authUserId = auth()->id();

        // 1. OBTENER INVITACIONES PENDIENTES
        // Buscamos eventos donde el usuario actual es un participante
        // y su estado en la tabla pivote es 'Pendiente'.
        $pendingInvitations = Event::whereHas('participants', function ($query) use ($authUserId) {
            $query->where('user_id', $authUserId)->where('status', 'Pendiente');
        })
        ->with('calendarEntry:id,title,start_datetime,entryable_id,entryable_type') // Cargamos datos del evento
        ->get();


        // 2. OBTENER ENTRADAS PARA MOSTRAR EN EL CALENDARIO
        $calendarEntries = CalendarEntry::query()
            // A) Entradas creadas por el usuario actual (Eventos o Tareas)
            ->where('user_id', $authUserId)
            // B) O eventos a los que el usuario fue invitado y ACEPTÓ
            ->orWhereHasMorph('entryable', [Event::class], function ($query) use ($authUserId) {
                $query->whereHas('participants', function ($subQuery) use ($authUserId) {
                    $subQuery->where('user_id', $authUserId)->where('status', 'Aceptado');
                });
            })
            // Cargamos las relaciones de forma eficiente
            ->with(['entryable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Event::class => ['participants:id,name'],
                    Task::class => [],
                ]);
            }])
            ->get();

        return Inertia::render('Calendar/Index', [
            'calendarEntries' => $calendarEntries,
            'pendingInvitations' => $pendingInvitations, // Pasamos las invitaciones a la vista
            'authUserId' => $authUserId,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Calendar/Create', [
            'users' => User::all(['id', 'name']),
            // Pasamos la fecha seleccionada desde la URL, si existe
            'selectedDate' => $request->query('date'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'entry_type' => 'required|in:Evento,Tarea',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after_or_equal:start_datetime',
            'location' => 'required_if:entry_type,Evento|nullable|string|max:255',
            'participants' => 'required_if:entry_type,Evento|array',
            'participants.*' => 'exists:users,id',
        ]);

        DB::transaction(function () use ($request) {
            $entryable = null;
            $currentUser = auth()->user();

            if ($request->entry_type === 'Evento') {
                $entryable = Event::create(['location' => $request->location]);
                
                // Sincronizamos a los participantes
                $entryable->participants()->sync($request->participants);

                // Si el creador está entre los participantes → marcar como Aceptado en el pivot
                if (in_array($currentUser->id, $request->participants)) {
                    $entryable->participants()->updateExistingPivot($currentUser->id, [
                        'status' => 'Aceptado',
                    ]);
                }
                
                // Creamos la entrada de calendario
                $entryable->calendarEntry()->create($request->only('title', 'description', 'start_datetime', 'end_datetime') + ['user_id' => $currentUser->id]);

                // --- LÓGICA DE NOTIFICACIÓN ---
                // Obtenemos los usuarios a notificar (todos los participantes menos el creador)
                $participantsToNotify = User::find(collect($request->participants)->reject(function ($id) use ($currentUser) {
                    return $id == $currentUser->id;
                }));

                // Enviamos la notificación a cada uno
                Notification::send($participantsToNotify, new EventInvitationNotification($entryable, $currentUser));
                
            } else {
                $entryable = Task::create(['status' => 'Pendiente']);
                $entryable->calendarEntry()->create($request->only('title', 'description', 'start_datetime', 'end_datetime') + ['user_id' => $currentUser->id]);
            }
        });

        return redirect()->route('calendar.index');
    }

    /**
     * Marca una tarea como completada.
     */
    public function updateTaskStatus(Task $task)
    {
        $task->update(['status' => 'Completada']);
        return redirect()->back();
    }

    /**
     * Actualiza el estado de la invitación de un usuario a un evento.
     */
    public function updateInvitationStatus(Request $request, Event $event)
    {
        // Validación de la entrada
        $request->validate(['status' => 'required|in:Aceptado,Rechazado']);
        
        $status = $request->status;
        $currentUser = Auth::user();

        // Actualizamos el estado en la tabla pivote para el usuario actual
        $event->participants()->updateExistingPivot($currentUser->id, [
            'status' => $status,
        ]);
        
        // Obtenemos al creador del evento a través de la relación polimórfica
        // Usamos load() para asegurar que la relación esté cargada y evitar consultas extra.
        $event->load('calendarEntry');
        $creator = User::find($event->calendarEntry->user_id);

        // Enviamos la notificación al creador (si no es el mismo usuario que responde)
        if ($creator && $creator !== $currentUser->id) {
            Notification::send($creator, new EventResponseNotification($event, $currentUser, $status));
        }        
        return redirect()->back();
    }

    /**
     * Elimina una entrada del calendario y su registro asociado (Evento o Tarea).
     */
    public function destroy(CalendarEntry $calendarEntry)
    {
        // La relación polimórfica se encargará de eliminar el Evento o Tarea asociada
        // si tienes configurado el borrado en cascada en la base de datos o en el modelo.
        // Por seguridad, lo hacemos explícito.
        DB::transaction(function () use ($calendarEntry) {
            $calendarEntry->entryable()->delete();
            $calendarEntry->delete();
        });

        return redirect()->route('calendar.index')->with('success', 'Entrada eliminada.');
    }
}
