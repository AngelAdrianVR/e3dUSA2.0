<?php

namespace App\Notifications;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewQuoteForApprovalNotification extends Notification
{
    use Queueable;

    protected $quote;

    /**
     * Create a new notification instance.
     */
    public function __construct(Quote $quote)
    {
        $this->quote = $quote;
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
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $quote_folio = 'COT-' . str_pad($this->quote->id, 4, "0", STR_PAD_LEFT);
        
        // Esta es la información que se guardará en la columna `data` de la tabla `notifications`.
        return [
            'title' => 'Cotización pendiente de autorización',
            'folio' => $quote_folio,
            'type' => 'quote_approval',
            'url' => route('quotes.show', $this->quote->id),
            'message' => "Nueva cotización con folio '{$quote_folio}' requiere autorización.",
            'icon' => 'fa-solid fa-file-invoice-dollar',
        ];
    }
}
