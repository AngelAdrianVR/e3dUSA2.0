<?php

namespace App\Notifications;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DuplicateContactsPendingNotification extends Notification
{
    use Queueable;

    protected Branch $branch;

    public function __construct(Branch $branch)
    {
        $this->branch = $branch;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->branch->name;

        return [
            'title' => 'Cliente con contactos repetidos por autorizar',
            'folio' => 'CLI-' . str_pad($this->branch->id, 4, '0', STR_PAD_LEFT),
            'type' => 'branch_duplicate_contacts',
            'url' => route('branches.show', $this->branch->id),
            'message' => "El cliente '{$client}' se registró con contactos repetidos. Revisa la justificación y autoriza o rechaza los contactos.",
            'icon' => 'fa-solid fa-user-shield',
        ];
    }
}
