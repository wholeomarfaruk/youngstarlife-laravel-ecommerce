<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CourierSettlement extends OpsModel
{
    protected $guarded = [];
    protected $casts = ['received_on' => 'date'];
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class, 'settlement_id'); }
    /** COD expected − courier charges − money actually received */
    public function getShortAttribute(): int { return max(0, $this->cod_total - $this->charges - $this->received); }
}
