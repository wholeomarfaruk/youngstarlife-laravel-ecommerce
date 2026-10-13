<?php

namespace App\Http\Middleware;

use App\Services\Storefront\DeviceManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Storefront: registers the visitor's device on the first visit (see DeviceManager). Admin panels and internal routes are skipped. */
class RegisterDevice
{
    public function __construct(private DeviceManager $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*', 'ops', 'ops/*', 'livewire/*', 'api/*', 'up', 'storage/*', 'optimize')) {
            try {
                $this->devices->boot($request);
            } catch (\Throwable $e) {
                report($e);   // device bookkeeping must never break a page
            }
        }

        return $next($request);
    }
}
