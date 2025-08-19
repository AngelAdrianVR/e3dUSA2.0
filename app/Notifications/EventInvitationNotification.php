<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Event;
use App\Models\User;

class EventInvitationNotification extends Notification
{
    use Queueable;

    protected $event;
    protected $inviter;

    /**
     * Create a new notification instance.
     */
    public function __construct(Event $event, User $inviter)
    {
        $this->event = $event;
        $this->inviter = $inviter;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // return ['database', 'mail']; // Para enviar correo agregar mail
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Esta es la información que se guardará en la columna `data`
        // de la tabla `notifications` en formato JSON.
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->calendarEntry->title,
            'inviter_id' => $this->inviter->id,
            'inviter_name' => $this->inviter->name,
            'message' => "{$this->inviter->name} te ha invitado al evento: {$this->event->calendarEntry->title}",
            // Icono de Font Awesome para el frontend
            'icon' => 'fa-solid fa-calendar-check',
            // URL genérica para la redirección
            'url' => route('calendar.index'),
        ];
        
        
    }
}
