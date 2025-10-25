<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SteadFast\SteadFastCourierLaravelPackage\Facades\SteadfastCourier;

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
        if (!$order->consignment_id) {
            $order->consignment_id = $consignmentId;
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

            if (Str::lower($status) === 'delivered') {
                $order->status = 'delivered';
                $order->delivery_date = now();

            } elseif (Str::lower($status) === 'partial_delivered') {
                $order->status = 'partial_delivered';
                $order->delivery_date = now();
            } elseif (Str::lower($status) === 'cancelled') {
                $order->status = 'cancelled';
                $order->cancelled_date = now();
            } elseif (Str::lower($status) === 'pending') {
                $order->status = 'in_transit';
            }
        }


        $order->save();

        // ✅ Success response
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received successfully.'
        ], 200);
    }
    public function place_order($id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 200);
        }
        if ($order->name == null || $order->phone == null || $order->address == null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid Order Details',
                'data' => $order
            ], 200);

        }
        $item_descriptions = '';
        foreach ($order->Order_Item as $item) {
            $item_descriptions .= $item->product->name . ' x ' . $item->quantity . ', ';
        }
        if ($order->consignment_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order Already Placed',
                'data' => $order
            ], 200);
        }

        $orderData = [
            'invoice' => $order->id,
            'recipient_name' => $order->name,
            'recipient_phone' => $order->phone,
            'recipient_address' => $order->address,
            'cod_amount' => floatval($order->total),
            'note' => 'Delivery charge must nite hobe. Inside Dhaka-70tk, outside dhaka-130, size problem hole marchent er sathe must kotha bolte hobe customer er samne.',
            'item_description' => $item_descriptions
        ];

        $response = SteadfastCourier::placeOrder($orderData);
        if ($response['status'] === 'error') {
            return response()->json([
                'status' => 'error',
                'message' => $response['message'],
                'data' => $order
            ], 200);
        }
        $order->consignment_id = $response['consignment']['consignment_id'];
        $order->status = 'in_review';
        $order->save();
        return response()->json([
            'status' => 'success',
            'message' => 'Order Placed Successfully.',
            'response' => $response,
            'data' => $order,
            'item_descriptions' => $item_descriptions

        ], 200);
    }
    public function place_bulk_order(Request $request)
    {
        $orders = Order::whereIn('id', $request->input('order_ids'))->get();
        $orderData = [];
        foreach ($orders as $order) {
            if (!$order->consignment_id && $order->name && $order->phone && $order->address && $order->total > 0  && strlen($order->phone) == 11) {
                $orderData[] = [
                    'invoice' => $order->id,
                    'recipient_name' => $order->name,
                    'recipient_phone' => $order->phone,
                    'recipient_address' => $order->address,
                    'cod_amount' => floatval($order->total),
                    'note' => '',
                    'item_description' => $order?->Order_Item?->pluck('product.name')->implode(', ')
                ];
            }

        }
        $response = SteadfastCourier::placeBulkOrder($orderData);
        if ($response['status'] == 404) {
            return response()->json([
                'status' => 'error',
                'message' => $response['message'],
                'response' => $response
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Orders Placed Successfully.',
            'response' => $response
        ], 200);
    }
}
