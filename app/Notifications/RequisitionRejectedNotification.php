<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionRejectedNotification extends Notification
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
        return [
            'title' => 'Requisición rechazada',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_rejected',
            'url' => route('requisitions.show', $this->requisition->id, false),
            'message' => "La requisición <b>{$this->requisition->folio}</b> fue rechazada. Motivo: {$this->requisition->rejection_reason}. Se debe re-trabajar sobre el mismo folio.",
            'icon' => 'fa-solid fa-circle-xmark',
        ];
    }
}
