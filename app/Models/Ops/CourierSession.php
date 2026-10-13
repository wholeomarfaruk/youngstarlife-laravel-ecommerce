<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** One meeting with a courier's pickup man: parcels sent, returns received, damaged parcels rejected. */
class CourierSession extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['closed_at' => 'datetime'];

    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function items(): HasMany { return $this->hasMany(CourierSessionItem::class)->latest('id'); }
    public function opener(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'opened_by'); }
    public function getIsOpenAttribute(): bool { return $this->status === 'open'; }

    /** ES-1009-01, ES-1009-02 … per day */
    public static function nextCode(): string
    {
        $prefix = 'ES-'.now()->format('md').'-';
        $n = static::where('code', 'like', $prefix.'%')->count() + 1;
        while (static::where('code', $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT))->exists()) $n++;
        return $prefix.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
