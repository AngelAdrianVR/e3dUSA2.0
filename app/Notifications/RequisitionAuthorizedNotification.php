<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\User;
use App\Support\RequisitionPermissions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionAuthorizedNotification extends Notification
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
        $label = $this->requisition->status;

        return [
            'title' => "Requisición: {$label}",
            'folio' => $this->requisition->folio,
            'type' => 'requisition_authorized',
            'url' => $notifiable instanceof User
                ? RequisitionPermissions::urlFor($notifiable, $this->requisition)
                : route('requisitions.index', [], false),
            'message' => "La requisición <b>{$this->requisition->folio}</b> pasó la validación y ahora está en etapa <b>{$label}</b>.",
            'icon' => 'fa-solid fa-circle-check',
        ];
    }
}
