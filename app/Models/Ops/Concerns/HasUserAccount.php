<?php

namespace App\Models\Ops\Concerns;

use App\Models\User;
use App\Services\Ops\UserAccounts;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Customer / Supplier profile linked to a user account: linked (or opened) on save, contact details kept in step. Draft placeholders stay unlinked. */
trait HasUserAccount
{
    protected static function bootHasUserAccount(): void
    {
        static::saving(function ($m) {
            if (! $m->user_id && ! str_starts_with((string) $m->phone, 'draft-')) $m->user_id = app(UserAccounts::class)->resolve($m)->id;
        });
        // only accounts opened for a profile follow it — a staff member's account is never renamed by a profile edit
        static::saved(function ($m) {
            if ($m->wasChanged(['name', 'phone']) && in_array($m->user?->role, ['customer', 'supplier'])) $m->user->forceFill(['name' => $m->name, 'phone' => $m->phone])->save();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
