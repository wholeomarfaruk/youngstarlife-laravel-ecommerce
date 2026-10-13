<?php
// Courier APIs (App\Services\Couriers). A courier in Settings → Couriers uses the driver whose key equals its code.
// Add a courier API: write a driver under app/Services/Couriers/Drivers (extends Driver) and register it here.

return [
    'drivers' => [
        'steadfast' => \App\Services\Couriers\Drivers\SteadfastDriver::class,
        'pathao' => \App\Services\Couriers\Drivers\PathaoDriver::class,
    ],

    'steadfast' => [
        // the Bearer token the old /api/steadfast/webhook used — still accepted so Steadfast keeps working until
        // its panel is switched to the new URL + token (Settings → Couriers). Clear it once that is done.
        'legacy_webhook_token' => env('STEADFAST_LEGACY_WEBHOOK_TOKEN', 'hwenfiwenr032u9404rj0iejwf90c9u4rn023rj0f'),
    ],

    // parcels the scheduler asks the courier about (ops:courier-sync): not final, booked / tracked, quiet for this long
    'sync_after_minutes' => 120,
    'sync_batch' => 100,
];
