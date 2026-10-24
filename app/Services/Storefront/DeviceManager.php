<?php

namespace App\Services\Storefront;

use App\Models\{Cart, Device, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The visitor's browser = a Device, identified by the long-lived ys_device cookie (encrypted by Laravel, so it can't be forged).
 * Registered on the first storefront request; the cart and orders use it; it is linked to a user account once one exists.
 * Singleton: current() is the device for this request (null for bots and skipped routes).
 */
class DeviceManager
{
    public const COOKIE = 'ys_device';
    protected const LIFETIME = 60 * 24 * 730;   // minutes → 2 years
    protected const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|meta-externalagent|whatsapp|telegram|preview|lighthouse|headless|pingdom|uptime|curl|wget|python|go-http|java\/|okhttp|axios|postman/i';

    protected ?Device $device = null;

    public function current(): ?Device { return $this->device; }

    public function isBot(?string $ua): bool { return ! $ua || preg_match(self::BOTS, $ua) === 1; }

    /** Finds the device from the cookie or registers a new one, refreshes last-seen (at most every 10 min) and re-sends the cookie. */
    public function boot(Request $request): ?Device
    {
        if ($this->isBot($request->userAgent())) return null;
        $token = $request->cookie(self::COOKIE);
        $device = is_string($token) && Str::isUuid($token) ? Device::where('token', $token)->first() : null;

        if (! $device) {
            $device = Device::create([
                'token' => (string) Str::uuid(), 'name' => $this->describe($request->userAgent()), 'model' => $this->platform($request->userAgent()),
                'user_agent' => $request->userAgent(), 'ip_address' => $request->ip(), 'status' => 'active', 'last_seen' => now(),
            ]);
            Cookie::queue(self::COOKIE, $device->token, self::LIFETIME, null, null, null, true, false, 'lax');
        } elseif (! $device->last_seen || $device->last_seen->lte($device->created_at) || $device->last_seen->lt(now()->subMinutes(10))) {
            // the first return (cookie kept) is always recorded, so storefront:prune-devices can tell real browsers from cookie-less clients
            $device->forceFill(['last_seen' => now(), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()])->saveQuietly();
            Cookie::queue(self::COOKIE, $device->token, self::LIFETIME, null, null, null, true, false, 'lax');   // sliding expiry
        }

        // signed in (login / register) → this browser belongs to that account
        if (($user = $request->user()) && $device->user_id !== $user->id) $this->linkUser($device, $user);

        return $this->device = $device;
    }

    /** Device (and its cart) now belong to the account (the account's Ops customer profile hangs off the same user). */
    public function linkUser(Device $device, User $user): void
    {
        $device->forceFill(['user_id' => $user->id])->saveQuietly();
        Cart::where('device_id', $device->id)->update(['user_id' => $user->id]);
    }

    /**
     * Browser signals from frontend/js/device-fp.js (+ this request's user agent) → SHA-256 kept on the device.
     * Ops Blacklist matches it to spot a blocked visitor who cleared cookies. Without model / GPU / canvas the rest is too common to tell browsers apart → none kept.
     */
    public function fingerprint(Device $device, array $signals, ?string $ua): void
    {
        $parts = ['ua' => (string) $ua];
        foreach (['model', 'os', 'screen', 'tz', 'lang', 'cpu', 'mem', 'touch', 'gpu', 'canvas'] as $k) $parts[$k] = is_scalar($signals[$k] ?? null) ? mb_substr((string) $signals[$k], 0, 255) : '';
        if ($parts['model'] === '' && $parts['gpu'] === '' && $parts['canvas'] === '') return;

        $fp = hash('sha256', json_encode($parts));
        if ($device->fingerprint !== $fp) $device->forceFill(['fingerprint' => $fp])->saveQuietly();
    }

    protected function describe(?string $ua): string
    {
        $browser = match (true) {
            (bool) preg_match('/FBAN|FBAV|FB_IAB/i', (string) $ua) => 'Facebook app', (bool) preg_match('/Instagram/i', (string) $ua) => 'Instagram app',
            (bool) preg_match('/Edg\//i', (string) $ua) => 'Edge', (bool) preg_match('/OPR\/|Opera/i', (string) $ua) => 'Opera', (bool) preg_match('/SamsungBrowser/i', (string) $ua) => 'Samsung Internet',
            (bool) preg_match('/Chrome|CriOS/i', (string) $ua) => 'Chrome', (bool) preg_match('/Firefox|FxiOS/i', (string) $ua) => 'Firefox', (bool) preg_match('/Safari/i', (string) $ua) => 'Safari',
            default => 'Browser',
        };
        return $browser.' on '.$this->platform($ua);
    }

    protected function platform(?string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Android/i', (string) $ua) => 'Android', (bool) preg_match('/iPhone|iPad|iPod/i', (string) $ua) => 'iOS',
            (bool) preg_match('/Windows/i', (string) $ua) => 'Windows', (bool) preg_match('/Macintosh|Mac OS/i', (string) $ua) => 'Mac',
            (bool) preg_match('/Linux/i', (string) $ua) => 'Linux', default => 'Other',
        };
    }
}
