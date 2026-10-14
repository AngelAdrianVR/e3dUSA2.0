<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Requisition $requisition)
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
        $origin = $this->requisition->origin === Requisition::ORIGIN_SELLER
            ? 'venta'
            : 'almacén / producción';

        return [
            'title' => 'Nueva requisición de material',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_created',
            'url' => route('requisitions.show', $this->requisition->id, false),
            'message' => "Nueva requisición <b>{$this->requisition->folio}</b> ({$this->requisition->type}) levantada por la vía de {$origin}.",
            'icon' => 'fa-solid fa-clipboard-list',
        ];
    }
}
