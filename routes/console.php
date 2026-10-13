<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// Ops panel: scheduled report emails + parcel delay / supplier due alerts (automations)
\Illuminate\Support\Facades\Schedule::command('ops:send-reports')->dailyAt('08:00');
\Illuminate\Support\Facades\Schedule::command('ops:alerts')->dailyAt('09:00');
// Storefront: drop one-visit devices that own nothing (cookie-less clients register one per request)
\Illuminate\Support\Facades\Schedule::command('storefront:prune-devices')->dailyAt('03:30');
// Users → Blacklist: expired phone entries release their customers (ops_customers.is_blocked)
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Services\Ops\Blacklist::class)->syncAll())->hourly()->name('ops-blacklist-sync');
// Ops → My profile: sign-in history older than 180 days
\Illuminate\Support\Facades\Schedule::command('model:prune', ['--model' => [\App\Models\Ops\LoginEvent::class, \App\Models\Ops\CourierApiLog::class, \App\Models\Ops\WebhookLog::class]])->dailyAt('03:45');
// Courier APIs: pull statuses of quiet parcels (safety net for missed webhooks)
\Illuminate\Support\Facades\Schedule::command('ops:courier-sync')->everyThirtyMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();
