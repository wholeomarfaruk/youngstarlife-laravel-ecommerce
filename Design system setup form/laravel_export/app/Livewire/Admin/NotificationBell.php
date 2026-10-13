<?php

namespace App\Livewire\Admin;

use Livewire\Component;

/** Polls every 30s only while the tab is visible (wire:poll.visible) → cheap. Uses Laravel database notifications. */
class NotificationBell extends Component
{
    public function markAllRead(): void { auth()->user()->unreadNotifications->markAsRead(); }

    public function render()
    {
        $items = auth()->user()->notifications()->latest()->limit(10)->get();
        return view('livewire.admin.notification-bell', ['items' => $items, 'unread' => $items->whereNull('read_at')->count()]);
    }
}
