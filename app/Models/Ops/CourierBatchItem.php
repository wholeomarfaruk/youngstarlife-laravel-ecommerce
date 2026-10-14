<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One editable row on a Bulk courier sheet (CourierBatch). */
class CourierBatchItem extends OpsModel
{
    public const DRAFT = 'draft';
    public const BOOKED = 'booked';
    public const FAILED = 'failed';

    protected $guarded = [];
    protected $casts = ['weight' => 'float', 'qty' => 'integer', 'cod' => 'integer', 'booked_at' => 'datetime'];

    public function batch(): BelongsTo { return $this->belongsTo(CourierBatch::class, 'courier_batch_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }

    public function scopeOrdered($q) { return $q->orderBy('sort_order')->orderBy('id'); }
    public function isBooked(): bool { return $this->status === self::BOOKED; }
}
