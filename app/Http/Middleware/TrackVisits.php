<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Stevebauman\Location\Facades\Location;

class TrackVisits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip admin panel routes
        // if ($request->is('admin/*')) {
        //     return $next($request);
        // }
        // $sessionId = session()->getId();

        // $position = Location::get($request->ip());

        // Visit::create([
        //     'session_id' => $sessionId,
        //     'ip_address' => $request->ip(),
        //     'device' => $this->detectDevice($request->userAgent()),
        //     'browser' => $this->detectBrowser($request->userAgent()),
        //     'os' => $this->detectOS($request->userAgent()),
        //     'referrer' => $request->headers->get('referer'),
        //     'page_url' => $request->fullUrl(),
        //     'country' => $position ? $position->countryName : 'Unknown',
        //     'city' => $position ? $position->cityName : 'Unknown',
        //     'timezone' => $position ? $position->timezone : 'UTC',
        // ]);
        return $next($request);
    }
    private function detectDevice($userAgent)
    {
        if (preg_match('/mobile/i', $userAgent))
            return 'Mobile';
        if (preg_match('/tablet/i', $userAgent))
            return 'Tablet';
        return 'Desktop';
    }

    private function detectBrowser($userAgent)
    {
        if (preg_match('/firefox/i', $userAgent))
            return 'Firefox';
        if (preg_match('/chrome/i', $userAgent))
            return 'Chrome';
        if (preg_match('/safari/i', $userAgent))
            return 'Safari';
        if (preg_match('/edge/i', $userAgent))
            return 'Edge';
        return 'Other';
    }

    private function detectOS($userAgent)
    {
        if (preg_match('/windows/i', $userAgent))
            return 'Windows';
        if (preg_match('/mac/i', $userAgent))
            return 'Mac';
        if (preg_match('/linux/i', $userAgent))
            return 'Linux';
        if (preg_match('/android/i', $userAgent))
            return 'Android';
        if (preg_match('/iphone|ipad/i', $userAgent))
            return 'iOS';
        return 'Other';
    }
}
