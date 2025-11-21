<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class InactiveClientsNotification extends Notification
{
    use Queueable;

    protected string $title;
    protected string $type;
    protected string $url;
    public Collection $branches;

    /**
     * Create a new notification instance.
     */
    public function __construct(Collection $branches)
    {
        $this->branches = $branches;
        $this->title = 'Alerta de Clientes Inactivos';
        $this->type = 'inactive_client';
        $this->url = route('companies.index');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Se prioriza el canal 'database' para alinear con la estructura de ApprovalQuoteNotification.
        // Puedes agregar 'mail' aquí si también deseas enviar correos.
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Clientes inactivos')
            ->markdown('emails.inactive-clients', [
                'greeting' => '¡Hola!',
                'intro' => "Se detectaron sucursales inactivas a las cuales no se les han generado cotizaciones, órdenes de venta ni muestras. A continuación se listan estas sucursales:",
                'url' => $this->url,
                'branches' => $this->branches,
                'salutation' => 'Recuerda reactivar el contacto con los clientes. Saludos',
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $branchCount = $this->branches->count();
        $branchNames = $this->branches->pluck('name')->implode(', ');

        // Se adapta la estructura de datos al formato de ApprovalQuoteNotification.
        return [
            'title' => $this->title,
            'folio' => $branchNames, // Usamos 'folio' para mostrar los nombres de las sucursales.
            'type' => $this->type,
            'url' => $this->url,
            'message' => "Tienes {$branchCount} sucursal(es) inactiva(s): {$branchNames}. ¡Es momento de contactarlas!",
            'icon' => 'fa-solid fa-user-clock', // Un icono más representativo.
        ];
    }
}
