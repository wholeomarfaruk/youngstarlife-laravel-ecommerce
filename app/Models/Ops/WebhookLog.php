<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Incoming courier webhook call (Settings → Integrations → Webhooks · delivery log). Kept 90 days. */
class WebhookLog extends OpsModel
{
    use Prunable;

    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime', 'payload' => 'array'];
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function prunable() { return static::where('created_at', '<', now()->subDays(90)); }
}
