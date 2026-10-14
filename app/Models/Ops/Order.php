<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Order extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['placed_at' => 'datetime', 'tags' => 'array', 'is_draft' => 'bool', 'attribution' => 'array'];

    protected static function booted(): void
    {
        // exchange orders: any status change (screen, webhook, sync, automation) moves the exchange lifecycle
        static::updated(function (Order $o) {
            if ($o->exchange_of_id && $o->wasChanged('status')) app(\App\Services\Ops\ExchangeService::class)->sync($o);
        });
    }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    /** the parcel that carries the order now (latest, not replaced by a re-send); latestOfMany() breaks column-list eager loads ('shipment:id,order_id') on MySQL */
    public function shipment(): HasOne { return $this->hasOne(Shipment::class)->whereNull('superseded_at')->latest('id'); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
    public function events(): HasMany { return $this->hasMany(OrderEvent::class)->latest('id'); }
    public function returns(): HasMany { return $this->hasMany(CustomerReturn::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function zone(): BelongsTo { return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id'); }
    public function exchangeOf(): BelongsTo { return $this->belongsTo(Order::class, 'exchange_of_id'); }
    public function exchanges(): HasMany { return $this->hasMany(Order::class, 'exchange_of_id')->latest('id'); }
    /** exchange order → its old-item return line (on the original order) */
    public function exchangeLine(): HasOne { return $this->hasOne(CustomerReturn::class, 'exchange_order_id')->latest('id'); }
    public function device(): BelongsTo { return $this->belongsTo(\App\Models\Device::class); }   // website orders: the visitor's browser
    public function creator(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
    /** Next order number: Settings → Orders prefix (default #) + highest number + 1 (ids can't be used: older drafts convert later). */
    public static function nextNumber(): string
    {
        $prefix = (string) ops_setting('orders.prefix', '#');
        $last = (int) static::where('is_draft', false)->where('number', 'like', $prefix.'%')->selectRaw('MAX(CAST(SUBSTRING(number, ?) AS UNSIGNED)) n', [mb_strlen($prefix) + 1])->value('n');
        return $prefix.($last ? $last + 1 : 1001);
    }
    /** find by what staff type / scan: "#1042", "1042", "YS-1042" */
    public function scopeNumbered($q, string $code)
    {
        $code = trim($code); $num = preg_replace('/^\D+/', '', $code); $prefix = (string) ops_setting('orders.prefix', '#');
        return $q->whereIn('number', array_unique([$code, '#'.$num, $prefix.$num]));
    }
    public function scopeLive($q) { return $q->where('is_draft', false); }
    public function scopeDrafts($q) { return $q->where('is_draft', true); }
    /**
     * An exchange (child record) as one step of its parent order, for the orders list: [label, tone].
     * pending → confirmed → packed → shipped → delivered (old item coming back) → done · returning (refused) · cancelled
     */
    public function exchangeStage(): array
    {
        return match ($this->exchange_state) {
            'cancelled' => ['Exchange cancelled', 'mute'], 'failed' => ['Exchange return received', 'bad'], 'completed' => ['Exchange done', 'ok'],
            'old_missing' => ['Exchange · old item missing', 'bad'], 'refused' => ['Exchange returning', 'bad'],
            'collecting' => ['Exchange delivered', 'info'], 'out' => ['Exchange shipped', 'info'],
            default => match ($this->status) {
                'pending' => ['Exchange pending', 'warn'], 'packed' => ['Exchange packed', 'info'], 'on_hold' => ['Exchange on hold', 'warn'],
                'cancelled' => ['Exchange cancelled', 'mute'], default => ['Exchange confirmed', 'info'],
            },
        };
    }
    /**
     * Partial delivery = two tracks at once: the kept part is delivered (order status), the rest comes back:
     * Partial returning → Partial return received (Return receive). [label, tone] or null.
     */
    public function partialReturnStage(): ?array
    {
        if ($this->status !== 'partially_delivered') return null;
        $s = $this->shipment;
        return $this->returns->contains(fn ($r) => ! $s || $r->shipment_id === $s->id) ? ['Partial return received', 'bad'] : ['Partial returning', 'warn'];
    }

    public function isOpenExchange(): bool { return in_array($this->exchange_state, ['awaiting', 'out', 'collecting', 'refused']) && $this->status !== 'cancelled'; }

    public function log(string $type, string $message): void { $this->events()->create(['type' => $type, 'message' => $message, 'user_id' => auth()->id()]); }
    public function hasTag(string $t): bool { return in_array($t, $this->tags ?? []); }
    public function tag(string $t): void { if (! $this->hasTag($t)) $this->update(['tags' => array_values(array_merge($this->tags ?? [], [$t]))]); }
}
