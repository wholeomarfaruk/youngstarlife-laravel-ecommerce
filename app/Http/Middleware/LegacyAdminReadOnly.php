<?php

namespace App\Http\Middleware;

use App\Support\LegacyAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Old /admin = read-only archive (App\Support\LegacyAdmin): forms and data-changing links are refused; browsing works. */
class LegacyAdminReadOnly
{
    /** still needed with the old admin closed: staff phone-push subscription, own password, and customer review
     *  screenshots (the storefront's reviews slider has no Ops screen yet) */
    protected const ALLOWED = ['admin.notifications.push.subscribe', 'admin.user.update', 'admin.notifications.read', 'admin.notifications.clear', 'admin.notifications.clear.all', 'admin.slides*'];

    /** GET links in the old admin that change data */
    protected const MUTATING_GETS = ['admin.orders.delete.soft', 'admin.orders.customer.block', 'admin.orders.customer.unblock', 'admin.steadfast.place_order', 'admin.auto.saved.orders.delete'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! LegacyAdmin::readOnly()) return $next($request);
        $route = $request->route()?->getName();
        $changes = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS']) || in_array($route, self::MUTATING_GETS);
        if (! $changes || \Illuminate\Support\Str::is(self::ALLOWED, (string) $route)) return $next($request);

        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => LegacyAdmin::MESSAGE], 403)
            : back()->with(['error' => LegacyAdmin::MESSAGE, 'status' => 'error', 'message' => LegacyAdmin::MESSAGE]);
    }
}
