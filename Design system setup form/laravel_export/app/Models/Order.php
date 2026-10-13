<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Order extends Model
{
    protected $guarded = [];
    protected $casts = ['placed_at' => 'datetime', 'tags' => 'array', 'is_draft' => 'bool'];
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function shipment(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Shipment::class)->latestOfMany(); }
    public function events(): HasMany { return $this->hasMany(OrderEvent::class)->latest('id'); }
    public function returns(): HasMany { return $this->hasMany(CustomerReturn::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function zone(): BelongsTo { return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id'); }
    public function scopeLive($q) { return $q->where('is_draft', false); }
    public function scopeDrafts($q) { return $q->where('is_draft', true); }
    public function log(string $type, string $message): void { $this->events()->create(['type' => $type, 'message' => $message, 'user_id' => auth()->id()]); }
}
