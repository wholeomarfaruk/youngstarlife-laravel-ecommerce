<?php

namespace App\Support;

/**
 * The old /admin panel is an archive since the website moved to Ops: it can be browsed, nothing in it changes data
 * (new orders, products and customers live in Ops). Switch with OPS_LEGACY_ADMIN_READ_ONLY=false if ever needed.
 */
class LegacyAdmin
{
    public const MESSAGE = 'The old admin is read-only now — new orders, products and customers are managed in Ops (/ops).';

    public static function readOnly(): bool { return (bool) config('ops.legacy_admin_read_only', true); }

    /** call first in an old-admin Livewire action that changes data */
    public static function guard(): void
    {
        if (self::readOnly()) abort(403, self::MESSAGE);
    }
}
