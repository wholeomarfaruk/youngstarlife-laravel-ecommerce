<?php

namespace App\Models\Ops;

use App\Models\{Device, User};
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Users → Blacklist: one phone / device / IP / email that is refused (block) or accepted but flagged (flag). App\Services\Ops\Blacklist does the matching. */
class BlacklistEntry extends OpsModel
{
    protected $table = 'ops_blacklist';
    protected $guarded = [];
    protected $casts = ['is_active' => 'bool', 'expires_at' => 'datetime', 'last_hit_at' => 'datetime', 'removed_at' => 'datetime'];

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function remover(): BelongsTo { return $this->belongsTo(User::class, 'removed_by'); }
    public function attempts(): HasMany { return $this->hasMany(BlacklistHit::class, 'blacklist_id'); }

    /** switched on and not expired */
    public function scopeActive($q) { return $q->where('is_active', true)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())); }
    public function scopeExpired($q) { return $q->where('is_active', true)->whereNotNull('expires_at')->where('expires_at', '<=', now()); }

    public function getIsLiveAttribute(): bool { return $this->is_active && (! $this->expires_at || $this->expires_at->isFuture()); }
    /** active | expired | removed */
    public function getStateAttribute(): string { return ! $this->is_active ? 'removed' : ($this->is_live ? 'active' : 'expired'); }
    /** what staff see: the phone / IP / email, or the device's browser name */
    public function getLabelAttribute(): string { return $this->type === 'device' ? ($this->device?->name ? $this->device->name.' #'.$this->value : 'Device #'.$this->value) : $this->value; }
}
