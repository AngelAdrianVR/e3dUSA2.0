<?php

namespace App\Notifications;

use App\Models\Branch;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DuplicateContactsRejectedNotification extends Notification
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
            'title' => 'Contactos repetidos rechazados',
            'folio' => 'CLI-' . str_pad($this->branch->id, 4, '0', STR_PAD_LEFT),
            'type' => 'branch_duplicate_contacts_rejected',
            'url' => route('branches.show', $this->branch->id),
            'message' => "Los contactos repetidos del cliente '{$client}' fueron rechazados y eliminados. Vuelve a capturarlos sin repetir teléfono o correo.",
            'icon' => 'fa-solid fa-user-xmark',
        ];
    }
}
