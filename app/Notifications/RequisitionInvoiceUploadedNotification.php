<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\User;
use App\Support\RequisitionPermissions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionInvoiceUploadedNotification extends Notification
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
        $amount = $this->requisition->invoice_amount
            ? number_format((float) $this->requisition->invoice_amount, 2) . ' ' . ($this->requisition->invoice_currency ?? '')
            : 'sin monto capturado';

        return [
            'title' => 'Factura recibida: pago pendiente',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_invoice',
            'url' => $notifiable instanceof User
                ? RequisitionPermissions::urlFor($notifiable, $this->requisition)
                : route('requisitions.index', [], false),
            'message' => "La requisición <b>{$this->requisition->folio}</b> tiene factura adjunta ({$amount}). Requiere pago.",
            'icon' => 'fa-solid fa-file-invoice',
        ];
    }
}
