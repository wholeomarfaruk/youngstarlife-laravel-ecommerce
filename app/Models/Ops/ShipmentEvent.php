<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a parcel's tracking timeline (courier webhook, API sync, booking, or a manual change). */
class ShipmentEvent extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
}
