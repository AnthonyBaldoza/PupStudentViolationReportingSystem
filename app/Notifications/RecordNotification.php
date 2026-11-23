<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Simple in-app (database) notification about a violation record.
 */
class RecordNotification extends Notification
{
    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?int $recordId = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'record_id' => $this->recordId,
        ];
    }
}
