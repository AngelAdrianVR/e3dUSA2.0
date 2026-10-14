<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequisitionCommentMentionedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Comment $comment,
        protected Requisition $requisition,
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
        $authorName = $this->comment->user?->name ?? 'Alguien';

        return [
            'title' => 'Te mencionaron en una requisición',
            'folio' => $this->requisition->folio,
            'type' => 'requisition_mentioned',
            'url' => route('requisitions.show', $this->requisition->id, false),
            'message' => "{$authorName} te mencionó en un comentario de la requisición <b>{$this->requisition->folio}</b>.",
            'icon' => 'fa-solid fa-at',
        ];
    }
}
