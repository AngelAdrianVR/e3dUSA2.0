<?php

namespace App\Notifications;

use App\Models\RequisitionShipment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionShipmentStatusNotification extends Notification
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
     */
    public function toArray(object $notifiable): array
    {
        $folios = $this->shipment->requisitions->pluck('folio')->implode(', ');
        $date = $this->shipment->estimated_arrival_date
            ? ' Fecha estimada de arribo: ' . $this->shipment->estimated_arrival_date->format('d/m/Y') . '.'
            : '';

        return [
            'title' => "Arribo de mercancía: {$this->shipment->status}",
            'folio' => $this->shipment->tracking_number,
            'type' => 'requisition_shipment',
            'url' => route('calendar.index', [], false) . '?tab=arrivals',
            'message' => "La guía <b>{$this->shipment->tracking_number}</b> cambió a <b>{$this->shipment->status}</b> ({$folios}).{$date}",
            'icon' => 'fa-solid fa-truck-fast',
        ];
    }
}
