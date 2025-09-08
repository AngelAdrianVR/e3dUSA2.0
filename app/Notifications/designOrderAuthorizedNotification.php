<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class designOrderAuthorizedNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $folio;
    protected $url;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $folio, $url)
    {
        $this->title = $title;
        $this->folio = $folio;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // La notificación solo se guardará en la base de datos
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
            'title' => $this->title,
            'folio' => $this->folio,
            'url' => $this->url,
            'message' => "La orden de diseño con folio '{$this->folio}' ha sido autorizada.",
            'icon' => 'fa-solid fa-check-circle', // ícono de FontAwesome
        ];
    }
}
