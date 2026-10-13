<?php
// Ops courier webhooks (api middleware → no CSRF). Registered in bootstrap/app.php.
// Each courier authenticates its own way (App\Services\Couriers\Drivers\*::authorizeWebhook) — see Settings → Couriers.

use App\Http\Controllers\Ops\CourierWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('ops/webhooks/courier/{code}', CourierWebhookController::class)->middleware('throttle:600,1')->name('ops.webhooks.courier');
