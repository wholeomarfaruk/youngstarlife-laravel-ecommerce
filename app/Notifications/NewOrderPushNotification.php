<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewOrderPushNotification extends Notification
{
    use Queueable;

    /** website orders are Ops orders (the old Order is still accepted by older screens) */
    protected Order|\App\Models\Ops\Order $order;

    public function __construct(Order|\App\Models\Ops\Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // webpush only — this is an instant alert, not a persisted "database"
        // notification (that's handled separately by PendingOrderNotification).
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New Order Received')
            ->icon(site()->faviconUrl())
            ->body(($this->order instanceof \App\Models\Ops\Order ? $this->order->ship_name.' · '.$this->order->number : $this->order->name) . ' placed an order — ৳' . $this->order->total)
            ->data(['url' => $this->order instanceof \App\Models\Ops\Order ? route('ops.orders.show', $this->order) : route('admin.orders.details', $this->order->id)])
            ->tag('order-' . $this->order->id)
            ->requireInteraction();
    }
}
