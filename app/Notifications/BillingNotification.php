<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingNotification extends Notification
{
    use Queueable;

    protected $sale;
    protected $messageContent;

    /**
     * Create a new notification instance.
     */
    public function __construct(Sale $sale, string $messageContent)
    {
        $this->sale = $sale;
        $this->messageContent = $messageContent;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Solo se guardará en la base de datos
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Opcional: Si en el futuro quieres enviar por correo
        return (new MailMessage)
                    ->subject('Acción de Facturación Requerida')
                    ->line($this->messageContent)
                    ->action('Ir al Dashboard de Cobranza', route('billing.dashboard'))
                    ->line('Gracias por usar nuestro ERP.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Esta información alimentará tu campana de notificaciones
        return [
            'title' => 'Facturación Requerida',
            'folio' => 'OV-' . $this->sale->id,
            'type' => 'facturacion',
            'url' => route('billing.dashboard'), // Los enviará directamente al nuevo dashboard
            'message' => $this->messageContent,
            'icon' => 'fa-solid fa-file-invoice-dollar text-red-500', // Ícono específico para este módulo
        ];
    }
}