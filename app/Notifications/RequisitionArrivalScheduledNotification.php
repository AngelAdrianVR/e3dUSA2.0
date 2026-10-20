<?php

namespace App\Notifications;

use App\Models\RequisitionShipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionArrivalScheduledNotification extends Notification
{
    use Queueable;

    public function __construct(protected RequisitionShipment $shipment)
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
     *
     * El enlace abre el calendario de arribos en la guía que registró la fecha.
     */
    public function toArray(object $notifiable): array
    {
        $folios = $this->shipment->requisitions->pluck('folio')->implode(', ');
        $date = $this->shipment->estimated_arrival_date?->format('d/m/Y');

        return [
            'title' => 'Fecha tentativa de arribo registrada',
            'folio' => $this->shipment->display_name,
            'type' => 'requisition_arrival_scheduled',
            'entry_key' => 'shipment-' . $this->shipment->id,
            'url' => route('calendar.index', [], false)
                . '?tab=arrivals&entry=shipment-' . $this->shipment->id,
            'message' => "La entrega <b>{$this->shipment->display_name}</b> tiene fecha tentativa de arribo: "
                . "<b>{$date}</b>" . ($folios !== '' ? " ({$folios})." : '.'),
            'icon' => 'fa-solid fa-calendar-check',
        ];
    }
}
