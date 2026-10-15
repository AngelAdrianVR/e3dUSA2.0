<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionQuotationUploadedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Requisition $requisition, protected bool $requiresAuthorization = true)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->requiresAuthorization
                ? 'Cotización pendiente de autorización'
                : 'Cotización registrada',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_quotation',
            'url' => route('requisitions.show', $this->requisition->id, false),
            'message' => $this->requiresAuthorization
                ? "La requisición <b>{$this->requisition->folio}</b> ya tiene cotización y requiere autorización."
                : "La requisición <b>{$this->requisition->folio}</b> ya tiene cotización registrada por la gerencia de compras.",
            'icon' => 'fa-solid fa-file-invoice-dollar',
        ];
    }
}
