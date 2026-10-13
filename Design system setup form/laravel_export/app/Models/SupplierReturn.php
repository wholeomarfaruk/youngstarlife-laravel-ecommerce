<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class SupplierReturn extends Model
{
    protected $guarded = [];
    protected $casts = ['step_dates' => 'array'];
    public const STEPS = ['pending', 'sent', 'received', 'approved', 'resolved'];
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function items(): HasMany { return $this->hasMany(SupplierReturnItem::class); }
    public function getValueAttribute(): int { return $this->items->sum(fn ($i) => $i->qty * $i->unit_cost); }
    public function getIsOpenAttribute(): bool { return ! in_array($this->status, ['resolved', 'rejected', 'cancelled']); }
}
