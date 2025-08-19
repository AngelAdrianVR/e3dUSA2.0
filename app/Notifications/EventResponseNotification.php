<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Event;
use App\Models\User;

class EventResponseNotification extends Notification
{
    use Queueable;

    protected $event;
    protected $responder;
    protected $status;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Event $event El evento al que se responde
     * @param \App\Models\User $responder El usuario que acepta o rechaza
     * @param string $status El estado de la respuesta ('Aceptado' o 'declined')
     */
    public function __construct(Event $event, User $responder, string $status)
    {
        $this->event = $event;
        $this->responder = $responder;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Siguiendo el ejemplo de tu otra notificación, usamos solo 'database'
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $actionText = $this->status;
        $icon = $this->status === 'Aceptado' ? 'fa-solid fa-user-check' : 'fa-solid fa-user-xmark';

        // Replicamos la estructura de tu EventInvitationNotification
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->calendarEntry->title,
            'responder_id' => $this->responder->id,
            'responder_name' => $this->responder->name,
            'message' => "{$this->responder->name} ha {$actionText} tu invitación al evento: {$this->event->calendarEntry->title}",
            // Icono de Font Awesome para el frontend, cambia según la respuesta
            'icon' => $icon,
            // URL para que el creador pueda ver los detalles del evento o los participantes
            'url' => route('calendar.index'), // O la ruta que prefieras, ej: route('events.show', $this->event->id)
        ];
    }
}
