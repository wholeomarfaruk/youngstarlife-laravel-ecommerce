<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/** Who did what (written by ops_log()). */
class ActivityLog extends OpsModel
{
    protected $table = 'ops_activity_log';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function subject(): MorphTo { return $this->morphTo(); }
}
