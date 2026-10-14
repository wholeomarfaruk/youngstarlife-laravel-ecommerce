<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** A parcel (P1005). */
class Shipment extends OpsModel
{
    /** courier statuses after which the courier sends nothing new (no polling) */
    public const FINAL = ['delivered', 'partial_delivered', 'returned', 'cancelled', 'lost'];

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updated(function (Shipment $s) {
            if (($s->wasChanged('courier_status') || $s->wasChanged('handed_over_at')) && ($o = $s->order) && $o->exchange_of_id) app(\App\Services\Ops\ExchangeService::class)->sync($o->fresh());
        });
    }
    protected $casts = ['packed_at' => 'datetime', 'handed_over_at' => 'datetime', 'delivered_at' => 'datetime', 'status_updated_at' => 'datetime',
        'booked_at' => 'datetime', 'last_tracked_at' => 'datetime', 'courier_meta' => 'array', 'superseded_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function settlement(): BelongsTo { return $this->belongsTo(CourierSettlement::class); }
    public function packer(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'packed_by'); }
    public function events(): HasMany { return $this->hasMany(ShipmentEvent::class)->latest('created_at')->latest('id'); }
    public function apiLogs(): HasMany { return $this->hasMany(CourierApiLog::class)->latest('created_at')->latest('id'); }

    /** next parcel code: P1001, P1002… */
    public static function nextCode(): string
    {
        $last = (int) static::where('code', 'like', 'P%')->selectRaw('MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)) n')->value('n');
        return 'P'.max(1001, $last + 1);
    }

    /** replaced by a re-send: still tracked, but it no longer moves the order */
    public function isSuperseded(): bool { return $this->superseded_at !== null; }

    public function isFinal(): bool { return in_array($this->courier_status, self::FINAL, true); }

    /** the courier's public tracking page for the customer */
    public function getTrackingUrlAttribute(): ?string
    {
        $phone = $this->order?->ship_phone;
        return match ($this->courier?->code) {
            'steadfast' => $this->tracking ? 'https://steadfast.com.bd/t/'.$this->tracking : null,
            'pathao' => ($id = $this->consignment_id ?: $this->tracking) ? 'https://merchant.pathao.com/tracking?'.http_build_query(array_filter(['consignment_id' => $id, 'phone' => $phone])) : null,
            'redx' => $this->tracking ? 'https://redx.com.bd/track-parcel/?trackingId='.$this->tracking : null,
            default => null,
        };
    }
}
