<?php

namespace App\Models\Ops;

use App\Models\Ops\Concerns\HasUserAccount;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends OpsModel
{
    use HasUserAccount;
    protected $guarded = [];
    protected $casts = ['last_order_at' => 'datetime', 'is_blocked' => 'bool', 'tags' => 'array'];
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function getDeliveryRateAttribute(): ?float { $d = $this->delivered_count + $this->returned_count; return $d ? $this->delivered_count / $d : null; }
    /** VIP (spent ≥ ৳12,000) · Repeat (3+ orders & ≥75%) · New (≤3 orders, first order this year) · Risky (<75%) · Has due */
    public function getSegmentsAttribute(): array
    {
        $r = $this->delivery_rate; $s = [];
        if ($this->total_spent >= 12000) $s[] = 'vip';
        if ($this->orders_count >= 3 && ($r ?? 0) >= .75) $s[] = 'repeat';
        if ($this->orders_count <= 3 && $this->created_at?->isCurrentYear()) $s[] = 'new';
        if ($r !== null && $r < .75) $s[] = 'risky';
        if ($this->due > 0) $s[] = 'due';
        return $s;
    }
    public function scopeReal($q) { return $q->where('phone', 'not like', 'draft-%'); }   // hide draft placeholders
}
