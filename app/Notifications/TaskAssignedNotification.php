<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $folio;
    protected $type;
    protected $url;
    protected $taskName;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $folio, $type, $url, $taskName)
    {
        $this->title = $title;
        $this->folio = $folio;
        $this->type = $type;
        $this->url = $url;
        $this->taskName = $taskName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Store only in the database
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // This is the information that will be saved in the `data` column
        // of the `notifications` table in JSON format.
        return [
            'title' => $this->title,
            'folio' => $this->folio,
            'type' => $this->type,
            'url' => $this->url,
            'message' => "Se te ha asignado la tarea '{$this->taskName}' para la producción con folio '{$this->folio}'.",
            'icon' => 'fa-solid fa-person-digging', // Example icon, you can change it
        ];
    }
}
