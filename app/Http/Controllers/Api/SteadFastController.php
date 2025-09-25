<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SteadFastController extends Controller
{
    function webhook(Request $request)
    {

        // ✅ Check Authorization header
        $authHeader = $request->header('Authorization');
        $expectedToken = 'Bearer ' . 'hwenfiwenr032u9404rj0iejwf90c9u4rn023rj0f'; // Replace with your actual token
        // return response()->json(['status' => 'error', 'message' => $request->header(), 'token' => $expectedToken], 403);

        if ($authHeader !== $expectedToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // ✅ Log the payload for debugging
        // Log::info('Webhook received:', $request->all());

        // ✅ Example handling
        $notificationType = $request->input('notification_type');
        $consignmentId = $request->input('consignment_id');
        $status = $request->input('status');
        $invoiceId = $request->input('invoice');
        $order = Order::find($invoiceId);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Invoice ID'
            ], 200);
        }


        // Make sure json_data is an array
        $data = $order->json_data ?? [];

        // Initialize 'steadfast' if not exists
        if (!isset($data['steadfast']) || !is_array($data['steadfast'])) {
            $data['steadfast'] = [];
        }

        // Append new webhook data
        $data['steadfast'][] = $request->all(); // automatically appended

        $order->json_data = $data;

        // You can process/save data to DB here...
        // Example: handle delivery status
        if ($notificationType === 'delivery_status') {
            // Do something with $consignmentId, $status etc.

            if ($status === 'Delivered') {
                $order->status = 'delivered';
                $order->delivery_date = now();

            } elseif ($status === 'partial_delivered') {
                // Do something with $consignmentId, $status etc.


                $order->status = 'partial_delivered';
                $order->pickup_date = now();


            } elseif ($status === 'Cancelled') {
                // Do something with $consignmentId, $status etc.


                $order->status = 'cancelled';
                $order->pickup_date = now();


            }
        }


        $order->save();

        // ✅ Success response
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received successfully.'
        ], 200);
    }
}
