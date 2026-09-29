<?php

namespace App\Http\Middleware;

use App\Support\Attribution;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the ad / UTM parameters a visitor landed with so the order can be attributed later.
 * Only reads the query string and sets first-party cookies; the URL, GTM and pixel are untouched.
 */
class CaptureAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCapture($request)) {
            try {
                $this->remember($request);
            } catch (\Throwable $e) {
                report($e); // attribution must never break a page
            }
        }

        return $next($request);
    }

    private function shouldCapture(Request $request): bool
    {
        return $request->isMethod('GET')
            && !$request->ajax()
            && !$request->expectsJson()
            && !$request->is('admin', 'admin/*', 'livewire/*', 'api/*', 'up', 'storage/*');
    }

    private function remember(Request $request): void
    {
        $touch = Attribution::capture($request);
        if (!$touch) {
            return;
        }

        $value = json_encode($touch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hasFirst = $request->hasCookie(Attribution::FIRST_COOKIE);
        $hasLast = $request->hasCookie(Attribution::LAST_COOKIE);

        if (!$hasFirst) {
            Cookie::queue(Attribution::FIRST_COOKIE, $value, Attribution::COOKIE_MINUTES);
        }
        // a new ad click is always the latest touch; a plain referral only fills an empty slot
        if ($touch['params'] || !$hasLast) {
            Cookie::queue(Attribution::LAST_COOKIE, $value, Attribution::COOKIE_MINUTES);
        }
    }
}
