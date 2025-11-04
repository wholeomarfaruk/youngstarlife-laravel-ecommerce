<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\PendingOrderNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function fetchPendingOrders()
    {
        $pendingOrders = Order::whereIn('status', [
            'pending',
            'confirmed',
            'on_hold',
            'processing',
            'ready',
        ])
        ->where('created_at', '<=', now()->subDays(3)) // older than 3 days
        ->whereDate('updated_at', '<', now()->toDateString())
        ->get();

        foreach ($pendingOrders as $order) {
            $order->updated_at = now();
            $order->save();
            auth()->user()->notify(new PendingOrderNotification($order));

        }
        return response()->json($pendingOrders);
    }
    public function UpdateNotification(Request $request)
    {
        $order = Order::find($request->id);
        $order->updated_at = now();
        $order->save();
        return response()->json(['success' => true,'message' => 'Notification Updated Successfully']);
    }
}
