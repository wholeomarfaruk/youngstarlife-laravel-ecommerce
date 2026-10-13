<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ops → Storefront → Overview → Store status "Maintenance mode": visitors see a closed page; staff, the panels, sign-in and webhooks keep working. */
class StorefrontStatus
{
    protected const OPEN = ['ops', 'ops/*', 'admin', 'admin/*', 'livewire/*', 'login', 'logout', 'register', 'password/*', 'two-factor-challenge', 'api/*', 'up', 'storage/*', 'facebook-product-feed.xml', 'sitemap.xml'];

    public function handle(Request $request, Closure $next): Response
    {
        if (site('storefront.status', 'live') !== 'maintenance' || $request->is(...self::OPEN)) return $next($request);
        $u = $request->user();
        if ($u && ($u->role === 'admin' || $u->ops_role)) return $next($request);   // staff preview the closed site
        return response()->view('storefront-maintenance', [], 503)->header('Retry-After', '1800');
    }
}
