<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewMaintenanceNotification extends Notification
{
    use Queueable;

    protected $machineName;
    protected $maintenanceType;
    protected $url;

    /**
     * Create a new notification instance.
     *
     * @param string $machineName
     * @param string $maintenanceType
     * @param string $url
     */
    public function __construct($machineName, $maintenanceType, $url)
    {
        $this->machineName = $machineName;
        $this->maintenanceType = $maintenanceType;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Solo se guardará en la base de datos
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Esta es la información que se guardará en la columna `data`
        // de la tabla `notifications` en formato JSON.
        return [
            'title' => 'Nuevo Mantenimiento Registrado',
            'machine_name' => $this->machineName,
            'maintenance_type' => $this->maintenanceType,
            'url' => $this->url,
            'message' => "Se ha registrado un nuevo mantenimiento '{$this->maintenanceType}' para la máquina '{$this->machineName}'. Ve a validarlo",
            'icon' => 'fa-solid fa-wrench',
        ];
    }
}
