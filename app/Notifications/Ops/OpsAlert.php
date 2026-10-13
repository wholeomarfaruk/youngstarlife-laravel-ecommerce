<?php

namespace App\Notifications\Ops;

use Illuminate\Notifications\Notification;

/** Ops bell notification (database). data: event, category, message, url */
class OpsAlert extends Notification
{
    public function __construct(public string $event, public string $category, public string $message, public ?string $url = null) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toArray(object $notifiable): array
    {
        return ['ops' => true, 'event' => $this->event, 'category' => $this->category, 'message' => $this->message, 'url' => $this->url];
    }
}
