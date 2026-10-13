<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Staff sign-in history (written by App\Services\Ops\LoginHistory). Kept 180 days. */
class LoginEvent extends OpsModel
{
    use Prunable;

    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime', 'new_device' => 'bool'];

    public function user(): BelongsTo { return $this->belongsTo(\App\Models\User::class); }
    public function prunable() { return static::where('created_at', '<', now()->subDays(180)); }

    /** "Chrome on Windows" */
    public function getDeviceAttribute(): string { return \App\Services\Ops\LoginHistory::describe($this->user_agent); }
}
