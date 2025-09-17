<?php

namespace App\Notifications;

use App\Models\SampleTracking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSampleTrackingNotification extends Notification
{
    use Queueable;

    protected $sampleTracking;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\SampleTracking $sampleTracking
     */
    public function __construct(SampleTracking $sampleTracking)
    {
        $this->sampleTracking = $sampleTracking;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Notificación solo en la base de datos
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  object  $notifiable
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Se asume que existe la relación 'requester' en el modelo SampleTracking
        $requesterName = $this->sampleTracking->requester->name ?? 'Usuario del sistema';
        $folio = 'SM-' . str_pad($this->sampleTracking->id, 4, "0", STR_PAD_LEFT);

        return [
            'title'   => 'Nueva Solicitud de Muestra',
            'folio'   => $folio,
            'url'     => route('sample-trackings.show', $this->sampleTracking->id),
            'message' => "{$requesterName} ha creado la solicitud '{$folio}'. Requiere autorización.",
            'icon'    => 'fa-solid fa-flask-vial',
        ];
    }
}
