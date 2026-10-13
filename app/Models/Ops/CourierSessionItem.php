<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierSessionItem extends OpsModel
{
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
    public const TYPES = ['sent' => ['Handed over', 'info'], 'returned' => ['Return received', 'ok'], 'rejected' => ['Damaged · not received', 'bad']];

    public function session(): BelongsTo { return $this->belongsTo(CourierSession::class, 'courier_session_id'); }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function customerReturn(): BelongsTo { return $this->belongsTo(CustomerReturn::class); }
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
}
