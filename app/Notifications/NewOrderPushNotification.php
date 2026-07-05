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

    protected Order $order;

    public function __construct(Order $order)
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
            ->icon(asset('frontend/img/youngstar logo-circle.png'))
            ->body($this->order->name . ' placed an order — ৳' . $this->order->total)
            ->data(['url' => route('admin.orders.details', $this->order->id)])
            ->tag('order-' . $this->order->id)
            ->requireInteraction();
    }
}
