<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Surfsidemedia\Shoppingcart\Facades\Cart;
use App\Models\products;
use App\Models\delivery_areas;
use App\Models\Order;
use App\Models\Order_Item;
use App\Models\Coupon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PhpParser\Node\Stmt\TryCatch;
use Validator;

class CartControllerTest extends Controller
{
    public function index()
    {
        $items = Cart::instance('cart')->content();
        return view('cart', compact('items'));
    }
    public function test()
    {


        return response()->json([
            'data' => Cart::instance('cart')->content(),
        ]);

    }
    public function cart_calculate($delivery_charge = 0, $cod_charge_percent = 0)
    {

        $items = Cart::instance('cart')->content();
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item->price * $item->qty;
        }
        $discount = 0;
        if (Session::has('coupon')) {
            if (Session::get('coupon')['type'] == 'fixed') {
                $discount = Session::get('coupon')['value'];
            } else {
                $discount = ($subtotal * Session::get('coupon')['value']) / 100;
            }
        }


        $total = $subtotal - $discount + $delivery_charge;
        $cod_charge = ($cod_charge_percent > 0) ? ($total * $cod_charge_percent / 100) : 0;
        $total = $total + $cod_charge;
        Session::put('mycart', [
            'total' => $total,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_charge' => $delivery_charge,
            'cod_charge' => $cod_charge,
            'cod_charge_percent' => $cod_charge_percent
        ]);
        return [
            'mycart' => [
                'total' => $total,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_charge' => $delivery_charge,
                'cod_charge' => $cod_charge,
                'cod_charge_percent' => $cod_charge_percent
            ]
        ];

    }

    public function add_to_cart(Request $request)
    {
        $product = products::find($request->id);

        if (!$product) {
            return redirect()->back()->with('error', 'Product not found.');
        }
        // Debug the sale_price

        if ($product->sale_price == null) {
            $product->sale_price = $product->regular_price;
        }
        Cart::instance('cart')->add($product->id, $product->name, $request->quantity, $product->sale_price)->associate('App\Models\Product');

        return redirect()->back()->with('status', 'Product Added To Cart');
    }

    public function cart_distroy()
    {
        Cart::instance('cart')->destroy();
        return "cart distried successfully";
    }


    public function add_json_to_cart(Request $request)
    {

        // Your existing code...
        $validated = $request->validate([
            'cartItems' => 'required|array',
            'cartItems.*.id' => 'required|integer',
            'cartItems.*.name' => 'required|string',
            'cartItems.*.weight' => 'required',
            'cartItems.*.quantity' => 'required|integer',
            'cartItems.*.price' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'delivery_charge' => 'required|numeric',
            'total' => 'required|numeric',
            'cod' => 'required|numeric',
        ]);




        $cartIcontent = Cart::instance('cart')->content();
        // Remove all items from the cart that are not in the JSON data.
        foreach ($cartIcontent as $item) {
            $found = false;

            // Check if the item is in the JSON data.
            foreach ($validated['cartItems'] as $cartItem) {
                if ($item->id == $cartItem['id']) {
                    $found = true;
                    break;
                }
            }
            // If the item is not found in the JSON data, remove it from the cart.
            if (!$found) {
                Cart::instance('cart')->remove($item->rowId);
            }

        }

        // Add the items from the JSON data to the cart.
        foreach ($validated['cartItems'] as $item) {
            $product = products::find($item['id']);
            $cart = Cart::instance('cart');
            $cartItem = $cart->search(function ($cartItem) use ($item) {
                return $cartItem->id === $item['id'];
            })->first();

            if ($cartItem) {
                $cart->update($cartItem->rowId, $item['quantity']);
            } else {
                Cart::instance('cart')->add($item['id'], $item['name'], $item['quantity'], $product->price, [], "0")->associate('App\Models\products');
            }
        }

        $this->cart_calculate($validated['delivery_charge'], $validated['cod']);


        $cartValue = Session::get('mycart');
        return response()->json([
            'success' => true,
            'message' => 'Items added to cart successfully!',
            'total' => $cartValue['total'],
            'subtotal' => $cartValue['subtotal'],
            'delivery_charge' => $cartValue['delivery_charge'],
            'cod_charge' => $cartValue['cod_charge'],
            'cod_charge_percent' => $cartValue['cod_charge_percent'],
            'discount' => $cartValue['discount'],
            'data' => Cart::instance('cart')->content(),
        ]);
    }




    public function remove_item(Request $request)
    {
        Cart::instance('cart')->remove($request->id);
        return redirect()->back();
    }

    public function increase_quantity($rowId)
    {

        $item = Cart::instance('cart')->get($rowId);
        Cart::instance('cart')->update($rowId, $item->qty + 1);
        return redirect()->back();
    }

    public function decrease_quantity($rowId)
    {
        $item = Cart::instance('cart')->get($rowId);
        Cart::instance('cart')->update($rowId, $item->qty - 1);
        return redirect()->back();
    }

    public function clear_cart()
    {
        Cart::instance('cart')->destroy();
        return redirect()->back();
    }

    public function calculate_discount()
    {
        $discount = 0;

        if (Session::has('coupon')) {
            // Ensure subtotal is converted to a proper numeric format
            $subtotal = floatval(str_replace(',', '', Cart::instance('cart')->subtotal()));

            // Determine discount based on coupon type
            if (Session::get('coupon')['type'] == 'fixed') {
                $discount = Session::get('coupon')['value'];
            } else {
                $discount = ($subtotal * Session::get('coupon')['value']) / 100;
            }

            // Calculate subtotal after discount
            $subtotalAfterDiscount = $subtotal - $discount;

            // Update session with discounts, keeping values as floats
            Session::put('discounts', [
                'discount' => number_format($discount, 2),  // Rounded for precision
                'subtotal' => number_format($subtotalAfterDiscount, 2),
                'total' => round($subtotalAfterDiscount, 2),
            ]);
        }
    }

    public function apply_coupon(Request $request)
    {
        if (isset($request->coupon_code)) {
            $coupon = Coupon::where('code', $request->coupon_code)->where('expiry_date', '>=', Carbon::now()->format('Y-m-d'))->first();
            if ($coupon) {
                Session::put('coupon', [
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'cart_value' => $coupon->cart_value,
                    'expiry_date' => $coupon->expiry_date
                ]);

                $this->calculate_discount();
                return redirect()->back()->with('coupon_status', 'Coupon Applied Successfully');
            } else {
                return redirect()->back()->with('coupon_error', 'Invalid Coupon Code');
            }
        } else {
            return redirect()->back()->with('error', 'Invalid Coupon Code');
        }


    }
    public function remove_coupon()
    {
        Session::forget('coupon');
        Session::forget('discounts');
        return redirect()->back()->with('coupon_status', 'Coupon Removed Successfully');
    }
    public function checkout()
    {

        return view('checkout');
    }
    public function place_order(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'delivery_area' => 'required',
        ]);

        try {
            //code...

            $product = products::find($request->product_id);
            $deliveryArea = delivery_areas::find($request->delivery_area);
            $deliveryCharge = $deliveryArea->charge;

            // Convert price and delivery charge to float for calculation
            $price = (float) $product->price;
            $quantity = (float) $request->quantity;
            $delivery = (float) $deliveryCharge;

            // Calculate total
            $total = ($price * $quantity) + $delivery;
            $order = new Order();
            $order->name = $request->name;
            $order->phone = $request->phone;
            $order->address = $request->address;
            $order->delivery_area_id = $deliveryArea->id ?? null;
            $order->cod_percentage = '0';
            $order->cod_charge = '0';
            $order->subtotal = $total;
            $order->total = $total ?? '0';
            $order->discount = '0';
            $order->fee = $deliveryArea->charge ?? 0;

            $order->is_paid = false;
            $order->status = 'pending';
            $order->save();

            $orderItem = new Order_Item();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $request->product_id;
            $orderItem->price = $product->price;
            $orderItem->quantity = $request->quantity;
            $orderItem->save();

            return redirect()->route('order.received', ['order' => $order->id]);
        } catch (\Throwable $th) {
            //throw $th;
            return $th->getMessage();
        }
    }
    public function order_received(Request $request)
    {

        $order = Order::find($request->order);
        if (!$order) {
            return redirect()->route('home.index');
        }
        $orderItems = Order_Item::where('order_id', $order->id)->get();
        $subtotal = 0;
        $orderItems->transform(function ($item) {
            $item->subtotal = $item->price * $item->quantity;
            return $item;
        });


        // return $orderItems;
        return view('order-received', compact('order'));
    }


        public function place_order_test(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'delivery_area' => 'required',
        ]);

        try {
            //code...

            $product = products::find($request->product_id);
            $deliveryArea = delivery_areas::find($request->delivery_area);
            $deliveryCharge = $deliveryArea->charge;

            // Convert price and delivery charge to float for calculation
            $price = (float) $product->price;
            $quantity = (float) $request->quantity;
            $delivery = (float) $deliveryCharge;

            // Calculate total
            $total = ($price * $quantity) + $delivery;
            $order = new Order();
            $order->name = $request->name;
            $order->phone = $request->phone;
            $order->address = $request->address;
            $order->delivery_area_id = $deliveryArea->id ?? null;
            $order->cod_percentage = '0';
            $order->cod_charge = '0';
            $order->subtotal = $total;
            $order->total = $total ?? '0';
            $order->discount = '0';
            $order->fee = $deliveryArea->charge ?? 0;

            $order->is_paid = false;
            $order->status = 'pending';
            $order->save();

            $orderItem = new Order_Item();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $request->product_id;
            $orderItem->price = $product->price;
            $orderItem->quantity = $request->quantity;
            $orderItem->save();

            return redirect()->route('order.received.test', ['order' => $order->id]);
        } catch (\Throwable $th) {
            //throw $th;
            return $th->getMessage();
        }
    }
    public function order_received_test(Request $request)
    {

        $order = Order::find($request->order);
        if (!$order) {
            return redirect()->route('home.index');
        }
        $orderItems = Order_Item::where('order_id', $order->id)->get();
        $subtotal = 0;
        $orderItems->transform(function ($item) {
            $item->subtotal = $item->price * $item->quantity;
            return $item;
        });
        // return $orderItems->first()->product->name;

        $dataLayer = [
            'event' => 'Purchase',
            'transaction_id' => $order->id,
            'content_name' => $orderItems->first()->product->name,
            'content_ids' => [$orderItems->first()->product_id],
            'content_type' => 'product',
            'value' => floatval($order->total),
            'currency' => 'BDT'
        ];
        // return $orderItems;
        return view('order-received-test', compact('order', 'orderItems','dataLayer'));
    }
}
