<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceOverdueNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $folio;
    protected $type;
    protected $url;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $folio, $type, $url)
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
        return [
            'title' => $this->title,
            'folio' => $this->folio,
            'type' => $this->type,
            'url' => $this->url,
            'message' => "La Factura con folio '{$this->folio}' ha vencido.",
            'icon' => 'fa-solid fa-file-invoice-dollar text-red-500',
        ];
    }
}
