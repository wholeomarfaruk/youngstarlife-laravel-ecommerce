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

    // ── parcel management: stuck / needs attention / contents

    /** statuses in which the courier should keep sending updates (pending only counts once it's booked or handed over) */
    public const MOVING = ['pending', 'picked_up', 'in_transit', 'out_for_delivery', 'hold'];

    /** Settings → Orders & delivery → "Parcel stuck after N days without a courier update" */
    public static function stuckDays(): int { return max(1, (int) ops_setting('orders.parcel_stuck_days', 3)); }

    /** with the courier and no status change for N days */
    public function isStuck(): bool
    {
        if ($this->isSuperseded() || ! in_array($this->courier_status, self::MOVING, true)) return false;
        if ($this->courier_status === 'pending' && ! $this->booked_at && ! $this->handed_over_at) return false;   // still with us, not the courier
        return ($this->status_updated_at ?? $this->updated_at)?->lt(now()->subDays(self::stuckDays())) ?? false;
    }

    public function scopeStuck($q)
    {
        $before = now()->subDays(self::stuckDays());
        return $q->whereNull('superseded_at')->whereIn('courier_status', self::MOVING)
            ->where(fn ($w) => $w->where('courier_status', '!=', 'pending')->orWhereNotNull('booked_at')->orWhereNotNull('handed_over_at'))
            ->where(fn ($w) => $w->where('status_updated_at', '<', $before)->orWhere(fn ($n) => $n->whereNull('status_updated_at')->where('updated_at', '<', $before)));
    }

    /** In courier → "Needs attention": stuck, on hold, failed delivery attempts, booking failed, or an open complaint */
    public function scopeNeedsAttention($q)
    {
        return $q->whereNull('superseded_at')->whereNotIn('courier_status', self::FINAL)->where(fn ($w) => $w
            ->whereIn('id', static::query()->stuck()->select('id'))
            ->orWhere('courier_status', 'hold')->orWhere('failed_attempts', '>', 0)
            ->orWhere(fn ($b) => $b->whereNull('booked_at')->whereNotNull('booking_error'))
            ->orWhereNotNull('complaint'));
    }

    /** why a parcel needs attention, for badges: ['Stuck 5 days', 'On hold', …] */
    public function attentionReasons(): array
    {
        if ($this->isSuperseded() || $this->isFinal()) return [];
        return array_values(array_filter([
            $this->isStuck() ? 'No update for '.(int) ($this->status_updated_at ?? $this->updated_at)->diffInDays(now()).' days' : null,
            $this->courier_status === 'hold' ? 'On hold' : null,
            $this->failed_attempts ? $this->failed_attempts.' failed attempt'.($this->failed_attempts > 1 ? 's' : '') : null,
            ! $this->booked_at && $this->booking_error ? 'Booking failed' : null,
            $this->complaint ? 'Complaint open' : null,
        ]));
    }

    /**
     * What went in this parcel, from the packed units (OrderItemAllocation.shipment_id): [[label, image, qty, batches => [[code, qty, confirmed]]]].
     * Parcels packed before units were stamped with their parcel fall back to the order's items (no batches).
     */
    public function contents(): array
    {
        $units = OrderItemAllocation::where('shipment_id', $this->id)->with('batch:id,code,variant_id', 'batch.variant.product:id,name,featured_image')->get();
        if ($units->isEmpty()) {
            return $this->order?->items()->with('variant.product:id,name,featured_image')->get()
                ->map(fn ($i) => ['label' => $i->variant?->label, 'image' => $i->variant?->product?->thumb_url, 'qty' => (int) $i->qty, 'batches' => []])->all() ?? [];
        }
        return $units->groupBy(fn ($a) => $a->batch?->variant_id)->map(function ($g) {
            $v = $g->first()->batch?->variant;
            return ['label' => $v?->label, 'image' => $v?->product?->thumb_url, 'qty' => (int) $g->sum('qty'),
                'batches' => $g->groupBy('batch_id')->map(fn ($b) => ['code' => $b->first()->batch?->code, 'qty' => (int) $b->sum('qty'), 'confirmed' => (bool) $b->contains('batch_scanned', true)])->values()->all()];
        })->values()->all();
    }

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
