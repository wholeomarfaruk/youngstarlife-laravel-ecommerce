<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Ops panel access: site admins, or active staff with an Ops role. Non-admin roles only reach the modules ticked in Settings → Users & roles. */
class OpsAccess
{
    /** module label for the current route (from config('ops.nav') match patterns) */
    public static function moduleFor(?string $route): ?string
    {
        foreach (config('ops.nav') as $item) foreach (explode('|', $item['match']) as $p) if ($route && \Illuminate\Support\Str::is($p, $route)) return $item['label'];
        return null;
    }

    public static function allowed($user, ?string $module): bool
    {
        if (! $module || $user->role === 'admin' || $user->ops_role === 'Admin') return true;
        return in_array($module, ops_setting('permissions.'.$user->ops_role, []) ?? []);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (! $u) return redirect()->route('login');
        abort_unless(($u->role === 'admin' || $u->ops_role) && $u->is_active, 403, 'No access to the Ops panel.');
        $route = $request->route()?->getName();
        if ($route !== 'ops.profile' && $route !== 'ops.dashboard' && ! static::allowed($u, static::moduleFor($route))) abort(403, 'Your role ('.$u->ops_role.') has no access to '.static::moduleFor($route).'.');
        if (! $u->last_seen_at || $u->last_seen_at->lt(now()->subMinutes(5))) $u->forceFill(['last_seen_at' => now()])->saveQuietly();
        return $next($request);
    }
}
