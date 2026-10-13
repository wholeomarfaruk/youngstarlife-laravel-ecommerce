<?php

use App\Http\Controllers\Ops\CourierWebhookController;
use App\Services\Couriers\CourierGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// old Steadfast callback URL (still set in the Steadfast panel) → the Ops courier webhook, so parcels in Ops get updated
Route::post('/steadfast/webhook', fn (Request $r, CourierGateway $g) => app(CourierWebhookController::class)($r, 'steadfast', $g))->name('steadfast.webhook');
