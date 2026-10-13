<?php

namespace App\Livewire\Ops;

use Livewire\Component;

/** Bell panel: All / Unread tabs, category filter, mark all read. Polls every 30s only while the tab is visible. */
class NotificationBell extends Component
{
    public string $tab = 'all';        // all | unread
    public string $category = '';      // '' | orders | stock | courier | returns | money | system

    public function markAllRead(): void { auth()->user()->unreadNotifications->markAsRead(); }
    public function open(string $id) { $n = auth()->user()->notifications()->findOrFail($id); $n->markAsRead(); $url = $this->urlOf($n); return $url !== '#' ? $this->redirect($url, navigate: str_starts_with($url, url('/ops'))) : null; }

    /** messages from the current site contain HTML (<a href>) → plain text + its URL */
    public function urlOf($n): string { return $n->data['url'] ?? (preg_match('/href="([^"]+)"/', (string) ($n->data['message'] ?? ''), $m) ? $m[1] : '#'); }

    public function render()
    {
        $q = auth()->user()->notifications()->latest();
        if ($this->tab === 'unread') $q->whereNull('read_at');
        if ($this->category) $q->where('data->category', $this->category);
        return view('livewire.ops.notification-bell', ['items' => $q->limit(20)->get(), 'unread' => auth()->user()->unreadNotifications()->count()]);
    }
}
