<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\User;
use App\Support\RequisitionPermissions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionPaidNotification extends Notification
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
            'title' => 'Pago de requisición registrado',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_paid',
            'url' => $notifiable instanceof User
                ? RequisitionPermissions::urlFor($notifiable, $this->requisition)
                : route('requisitions.index', [], false),
            'message' => "El pago de la requisición <b>{$this->requisition->folio}</b> fue registrado. Se comparte el comprobante con el proveedor y se solicita fecha de entrega.",
            'icon' => 'fa-solid fa-money-bill-wave',
        ];
    }
}
