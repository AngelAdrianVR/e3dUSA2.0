<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SampleAuthorizedNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $folio;
    protected $url;

    /**
     * Create a new notification instance.
     *
     * @param string $title El título de la notificación.
     * @param string $folio El folio o ID del seguimiento.
     * @param string $url La URL a la que se redirigirá al usuario.
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
        // Se enviará a la base de datos para mostrarla en la interfaz
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Este método es opcional si no enviarás correos electrónicos.
        return (new MailMessage)
                    ->subject($this->title)
                    ->line("El seguimiento de muestra con folio '{$this->folio}' ha sido autorizado.")
                    ->action('Ver Seguimiento', $this->url)
                    ->line('¡Gracias por usar nuestra aplicación!');
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
            'message' => "El seguimiento de muestra con folio '{$this->folio}' ha sido autorizado.",
            'icon' => 'fa-solid fa-check-circle', // Ícono de FontAwesome para la interfaz
        ];
    }
}
