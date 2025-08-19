<?php

namespace App\Http\Controllers;

use App\Models\CalendarEntry;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CalendarController extends Controller
{
    public function index()
    {
        $calendarEntries = CalendarEntry::with([
            // Cargamos la relación polimórfica 'entryable'
            'entryable' => function (MorphTo $morphTo) {
                // Y definimos qué relaciones anidadas cargar para cada tipo de modelo
                $morphTo->morphWith([
                    Event::class => ['participants' => function ($query) {
                        // Si es un Evento, carga sus participantes
                        $query->select('users.id', 'users.name');
                    }],
                    // Si es una Tarea, no necesitamos cargar ninguna relación extra.
                    Task::class => [], 
                ]);
            }
        ])->get();

        return Inertia::render('Calendar/Index', [
            'calendarEntries' => $calendarEntries,
            'authUserId' => Auth::id(),
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
            if ($request->entry_type === 'Evento') {
                $entryable = Event::create(['location' => $request->location]);
                $entryable->participants()->sync($request->participants);
            } else {
                $entryable = Task::create(['status' => 'Pendiente']);
            }
            $entryable->calendarEntry()->create($request->only('title', 'description', 'start_datetime', 'end_datetime') + ['user_id' => auth()->id()]);
        });

        return redirect()->route('calendar.index')->with('success', 'Entrada agendada.');
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
        $request->validate(['status' => 'required|in:accepted,declined']);
        
        $event->participants()->updateExistingPivot(Auth::id(), [
            'status' => $request->status,
        ]);
        
        return redirect()->back()->with('success', 'Respuesta enviada.');
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
