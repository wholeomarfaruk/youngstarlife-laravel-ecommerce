<?php

namespace App\Services\Ops;

use App\Models\Ops\LoginEvent;
use App\Models\User;
use App\Notifications\Ops\OpsAlert;

/** Records staff sign-ins (listeners in AppServiceProvider) and warns the person in their bell when a new device signs in. */
class LoginHistory
{
    public function record(?User $user, string $event): void
    {
        if (! $user || ! ($user->role === 'admin' || $user->ops_role)) return;   // staff only — customers don't use the panel
        try {
            $ua = mb_substr((string) request()->userAgent(), 0, 255);
            $new = $event === 'login' && LoginEvent::where('user_id', $user->id)->where('event', 'login')->exists()
                && ! LoginEvent::where('user_id', $user->id)->where('event', 'login')->where('user_agent', $ua)->exists();
            LoginEvent::create(['user_id' => $user->id, 'event' => $event, 'ip' => request()->ip(), 'user_agent' => $ua, 'new_device' => $new]);
            if ($new) $user->notify(new OpsAlert('security', 'system', 'New sign-in to your account: '.static::describe($ua).' · '.request()->ip().'. Not you? Change your password.', route('ops.profile', ['tab' => 'security'])));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** "Chrome on Windows" from a user agent */
    public static function describe(?string $ua): string
    {
        if (! $ua) return 'Unknown device';
        $browser = collect(['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser' => 'Samsung Internet', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'])->first(fn ($l, $k) => str_contains($ua, $k)) ?? 'Browser';
        $os = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])->first(fn ($l, $k) => str_contains($ua, $k)) ?? 'unknown OS';
        return "$browser on $os";
    }
}
