<?php

namespace App\Http\Controllers;

use App\Services\Storefront\DeviceManager;
use Illuminate\Http\Request;

/** POST from frontend/js/device-fp.js: the browser's signals → fingerprint on the visitor's device (no device for bots → ignored). */
class DeviceFingerprintController extends Controller
{
    public function __invoke(Request $request, DeviceManager $devices)
    {
        $data = $request->validate(['s' => 'required|array']);
        if ($device = $devices->current()) $devices->fingerprint($device, $data['s'], $request->userAgent());
        return response()->noContent();
    }
}
