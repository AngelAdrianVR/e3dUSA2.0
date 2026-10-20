<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\User;
use App\Support\RequisitionPermissions;
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
            'url' => $notifiable instanceof User
                ? RequisitionPermissions::urlFor($notifiable, $this->requisition)
                : route('requisitions.index', [], false),
            'message' => "La requisición <b>{$this->requisition->folio}</b> fue rechazada. Motivo: {$this->requisition->rejection_reason}. " . ($this->requisition->type === Requisition::TYPE_IMPORT
                ? 'Corrígela con «Editar requisición» o vuelve a subir la cotización, y registra de nuevo la validación (Pass / Fail) sobre el mismo folio.'
                : 'Edita la requisición para corregirla y volver a habilitar las acciones sobre el mismo folio.'),
            'icon' => 'fa-solid fa-circle-xmark',
        ];
    }
}
