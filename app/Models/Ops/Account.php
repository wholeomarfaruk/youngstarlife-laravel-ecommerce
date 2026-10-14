<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, MorphTo};

class Account extends OpsModel
{
    protected $guarded = [];
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }

    /** id of a money account by name (Cash, Bank, bKash, Nagad — config('ops.accounts')); created on first use so a payment never posts without one */
    public static function idFor(string $name): int { return static::firstOrCreate(['name' => $name])->id; }
}
