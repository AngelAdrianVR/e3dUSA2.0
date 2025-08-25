<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\CalendarEntry;
use Carbon\Carbon;

class CalendarReminderNotification extends Notification
{
    use Queueable;

    protected $calendarEntry;
    protected $reminderType; // 'today' o 'tomorrow'

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\CalendarEntry $calendarEntry
     * @param string $reminderType
     */
    public function __construct(CalendarEntry $calendarEntry, string $reminderType)
    {
        $this->calendarEntry = $calendarEntry;
        $this->reminderType = $reminderType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $greeting = 'Hola ' . $notifiable->name . ',';
        $line = $this->reminderType === 'today'
            ? "Este es un recordatorio de que tu evento/tarea '{$this->calendarEntry->title}' es hoy."
            : "Este es un recordatorio para tu próximo evento o tarea '{$this->calendarEntry->title}'.";

        return (new MailMessage)
                    ->subject('Recordatorio: ' . $this->calendarEntry->title)
                    ->greeting($greeting)
                    ->line($line)
                    ->line('**Fecha de inicio:** ' . $this->calendarEntry->start_datetime->format('d/m/Y H:i A'))
                    ->action('Ver en Calendario', url('/calendar')) // Ajusta esta URL a tu ruta del calendario
                    ->line('¡Que tengas un excelente día!');
    }

    /**
     * Get the array representation of the notification for the database.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->reminderType === 'today'
            ? "Tu evento/tarea '{$this->calendarEntry->title}' es hoy."
            : "Tu evento/tarea '{$this->calendarEntry->title}' comienza mañana.";

        return [
            'title' => 'Recordatorio: ' . $this->calendarEntry->title,
            'message' => $message,
            'icon' => 'fa-solid fa-calendar-day',
            'url' => '/calendar',
            'entry_id' => $this->calendarEntry->id,
        ];
    }
}
