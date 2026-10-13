<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Transaction extends Model
{
    protected $guarded = [];
    protected $casts = ['date' => 'date'];
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
    protected static function booted(): void
    {
        static::created(fn ($t) => $t->account()->increment('balance', $t->direction === 'in' ? $t->amount : -$t->amount));
    }
}
