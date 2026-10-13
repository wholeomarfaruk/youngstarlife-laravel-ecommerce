<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every call this app made to a courier API (App\Services\Couriers\CourierGateway). Kept 90 days. */
class CourierApiLog extends OpsModel
{
    use Prunable;

    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime', 'success' => 'bool', 'request' => 'array', 'response' => 'array'];
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function prunable() { return static::where('created_at', '<', now()->subDays(90)); }
}
