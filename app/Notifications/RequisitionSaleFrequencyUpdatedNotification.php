<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\User;
use App\Support\RequisitionPermissions;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionSaleFrequencyUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Requisition $requisition,
        protected ?string $previousFrequency,
    ) {
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
        $product = $this->requisition->items()->with('product:id,name')->first()?->product?->name
            ?? $this->requisition->business_name
            ?? $this->requisition->branch?->name
            ?? 'la requisición';

        $previous = $this->previousFrequency ?: 'Sin definir';
        $current = $this->requisition->sale_frequency ?: 'Sin definir';

        return [
            'title' => 'Frecuencia de consumo actualizada',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_sale_frequency',
            'url' => $notifiable instanceof User
                ? RequisitionPermissions::urlFor($notifiable, $this->requisition, ['tab' => 'items'])
                : route('requisitions.index', [], false),
            'message' => "La frecuencia de consumo de <b>{$this->requisition->folio}</b> ({$product}) "
                . "cambió de <b>{$previous}</b> a <b>{$current}</b>.",
            'icon' => 'fa-solid fa-arrows-rotate',
        ];
    }
}
