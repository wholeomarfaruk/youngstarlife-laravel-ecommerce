<?php
// routes/web.php: require __DIR__.'/webhooks.php';  (exclude 'webhooks/*' from CSRF in bootstrap/app.php)

use App\Models\{Courier, Shipment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/courier/{code}', function (Request $r, string $code) {
    $courier = Courier::where('code', $code)->firstOrFail();
    abort_unless(hash_equals((string) $courier->webhook_secret, (string) $r->header('X-Webhook-Secret')), 401);
    // Map each courier's payload to our 11 courier statuses
    $map = ['delivered' => 'delivered', 'partial_delivered' => 'partial_delivered', 'cancelled' => 'cancelled', 'hold' => 'hold', 'in_review' => 'pending', 'pending' => 'in_transit'];
    $s = Shipment::where('tracking', $r->input('tracking_code', $r->input('consignment_id')))->firstOrFail();
    $status = $map[$r->input('status')] ?? $s->courier_status;
    $s->update(['courier_status' => $status, 'status_updated_at' => now(), 'delivered_at' => $status === 'delivered' ? now() : $s->delivered_at]);
    if ($status === 'delivered') { $s->order->update(['status' => 'delivered', 'payment_status' => 'paid']); $s->order->customer->increment('delivered_count'); }
    if (in_array($status, ['cancelled', 'returning'])) $s->order->update(['status' => 'returning']);
    $s->order->log('courier', $courier->name.' → '.$status);
    return response()->noContent();
})->name('webhooks.courier');
