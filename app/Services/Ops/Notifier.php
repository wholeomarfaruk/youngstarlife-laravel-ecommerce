<?php

namespace App\Services\Ops;

use App\Models\User;
use App\Notifications\Ops\OpsAlert;
use Illuminate\Support\Facades\Notification;

/** Sends Ops events to the bell of every active Ops user, honouring Settings → Web push (in-app column) and each person's My profile → Notifications. */
class Notifier
{
    public function send(string $event, string $message, ?string $url = null): void
    {
        if (! (ops_setting("push.matrix.$event.app") ?? true)) return;
        $category = config("ops.notify_events.$event.1", 'system');
        $users = User::query()->where(fn ($q) => $q->where('role', 'admin')->orWhereNotNull('ops_role'))->where('is_active', true)->get()->filter->wantsOps($event);
        Notification::send($users, new OpsAlert($event, $category, $message, $url));
    }
}
