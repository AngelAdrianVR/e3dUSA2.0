<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Sale; // Importamos el modelo Sale

class SaleAuthorizedNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $folio;
    protected $type;
    protected $url;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $folio, string $type, string $url)
    {
        $this->title = $title;
        $this->folio = $folio;
        $this->type = $type;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // La notificación se guardará en la base de datos
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
            'type' => $this->type, // 'sale' o 'stock'
            'url' => $this->url,
            'message' => "La orden '{$this->folio}' ha sido autorizada. Por favor, da seguimiento.",
            'icon' => 'fa-solid fa-check-circle', // Puedes cambiar el ícono si lo deseas
        ];
    }
}
