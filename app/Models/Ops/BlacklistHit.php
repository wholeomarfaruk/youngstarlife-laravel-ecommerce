<?php

namespace App\Models\Ops;

use App\Models\{Device, User};
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One order attempt that matched a blacklist entry (refused or flagged). */
class BlacklistHit extends OpsModel
{
    protected $table = 'ops_blacklist_hits';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['blocked' => 'bool', 'created_at' => 'datetime'];

    public function entry(): BelongsTo { return $this->belongsTo(BlacklistEntry::class, 'blacklist_id'); }
    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
