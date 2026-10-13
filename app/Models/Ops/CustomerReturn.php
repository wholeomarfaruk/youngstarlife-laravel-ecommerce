<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CustomerReturn extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['photos' => 'array', 'refunded_at' => 'datetime', 'received_at' => 'datetime'];
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function items(): HasMany { return $this->hasMany(CustomerReturnItem::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function exchangeOrder(): BelongsTo { return $this->belongsTo(Order::class, 'exchange_order_id'); }
    public function receiver(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'received_by'); }
}
