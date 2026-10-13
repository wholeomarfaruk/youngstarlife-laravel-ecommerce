<?php

namespace App\Models\Ops;

use App\Services\Couriers\CourierGateway;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Courier extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['credentials' => 'encrypted:array', 'is_active' => 'bool', 'last_synced_at' => 'datetime'];
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
    public function settlements(): HasMany { return $this->hasMany(CourierSettlement::class); }
    public function zone(): BelongsTo { return $this->belongsTo(DeliveryZone::class, 'default_zone_id'); }
    public function webhookLogs(): HasMany { return $this->hasMany(WebhookLog::class); }
    public function apiLogs(): HasMany { return $this->hasMany(CourierApiLog::class); }

    /** API key shown masked: ••••••3f9a (Steadfast api_key / Pathao client_id) */
    public function getMaskedKeyAttribute(): string { $k = (string) ($this->credentials['api_key'] ?? $this->credentials['client_id'] ?? ''); return $k ? str_repeat('•', 6).substr($k, -4) : '—'; }

    /** a driver exists for this courier code (config/couriers.php) */
    public function hasApi(): bool { return CourierGateway::supports($this->code); }

    /** every required credential field of its driver is filled */
    public function isConnected(): bool
    {
        if (! $this->hasApi()) return false;
        foreach (CourierGateway::fields($this->code) as $f) if (($f['required'] ?? false) && blank($this->credentials[$f['key']] ?? null)) return false;
        return true;
    }

    public function can(string $capability): bool { return $this->isConnected() && (CourierGateway::capabilities($this->code)[$capability] ?? false); }

    /** the URL to paste in the courier's merchant panel (secret travels in a header for Steadfast / Pathao, in ?secret= for others) */
    public function webhookUrl(): string
    {
        $url = route('ops.webhooks.courier', $this->code);
        return in_array($this->code, ['steadfast', 'pathao']) || ! $this->webhook_secret ? $url : $url.'?secret='.$this->webhook_secret;
    }
}
