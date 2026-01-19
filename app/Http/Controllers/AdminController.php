<?php

namespace App\Http\Controllers;
use ShahariarAhmad\CourierFraudCheckerBd\Services\PathaoService;
use ShahariarAhmad\CourierFraudCheckerBd\Services\SteadfastService;
use App\Exports\OrderExport;
use App\Models\Analytic;
use App\Models\Size;
use App\Models\Visit;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\products;
use App\Models\delivery_areas;
use App\Models\Order;
use App\Models\Order_Item;
use App\Models\User;
use App\Models\Slide;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Media;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use ShahariarAhmad\CourierFraudCheckerBd\Facade\CourierFraudCheckerBd;

class AdminController extends Controller
{
    public function index()
    {
        //pending
        $pending_orders = Order::where('status', 'pending')->count();
        $pending_orders_sum = Order::where('status', 'pending')->sum('total');
        //delivered
        $delivered_orders = Order::where('status', 'delivered')->count();
        $delivered_orders_sum = Order::where('status', 'delivered')->sum('total');
        //cancelled
        $cancelled_orders = Order::where('status', 'cancelled')->count();
        $cancelled_orders_sum = Order::where('status', 'cancelled')->sum('total');
        //total
        $total_orders = Order::count();
        $total_orders_sum = Order::sum('total');

        $orders = Order::orderBy('created_at', 'desc')->limit(10)->get();

        return view('admin.index', compact('pending_orders', 'delivered_orders', 'cancelled_orders', 'total_orders', 'pending_orders_sum', 'delivered_orders_sum', 'cancelled_orders_sum', 'total_orders_sum', 'orders'));
    }
    public function login()
    {
        return view('admin.login');
    }


    //Products
    public function products(Request $request)
    {
        $search = $request->search;
        if ($search) {
            $products = products::where('name', 'like', '%' . $search . '%')
            ->orWhere('id', 'like', '%' . $search . '%')
            ->orWhere('price', 'like', '%' . $search . '%')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        }else{

            $products = products::orderBy('created_at', 'desc')->paginate(20);
        }
        return view('admin.products', compact('products'));
    }

    public function productsAdd()
    {
        return view('admin.products-add');
    }
    public function productStore(Request $request)
    {
        // return $request->all();
        $request->validate([
            'name' => 'required',
            'price' => 'required|numeric',
            'stock_status' => 'required|in:in_stock,out_of_stock',
            'quantity' => 'required|integer',
            'image' => 'mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        try {
            //code...

            $product = new products();

            $product->name = $request->name;

            $product->price = $request->price;
            if ($request->discount_price) {
                $product->discount_price = $request->discount_price;
            }
            if ($request->name) {
                $slug = Str::slug($request->name);
                if (products::where('slug', $slug)->exists()) {
                    $slug = $slug . '-' . Carbon::now()->timestamp;
                }
                $product->slug = $slug;
            }
            $product->featured = $request->featured ? true : false;

            if ($request->sku) {
                $product->sku = $request->sku;
            }
            $product->stock_status = $request->stock_status;

            $product->quantity = $request->quantity;



            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $extension = $image->getClientOriginalExtension();
                $filename = Carbon::now()->timestamp . "." . $extension;
                $this->generateProductThumbnailImage($image, $filename);
                $product->image = $filename;
            }

            if ($request->description) {
                $product->description = $request->description;
            }
            if ($request->short_description) {
                $product->short_description = $request->short_description;
            }
            if ($request->has('status')) {
                $product->status = $request->status ? true : false;
            }

            $product->save();

            if ($request->has('sizes')) {

                foreach ($request->sizes as $key => $size) {
                    $size = Size::create([
                        'products_id' => $product->id,
                        'name' => $size
                    ]);
                }
            }
            if ($request->hasFile('images')) {

                // Store file in 'public/media'
                $images = $request->file('images');
                $path = 'storage/images/products/' . $product->id . '/';
                if (!file_exists(public_path($path))) {
                    mkdir(public_path($path), 0777, true);
                }
                foreach ($images as $key => $file) {

                    // Save in media table

                    $media = new Media();
                    $media->filename = basename($file->getClientOriginalName());
                    $media->original_name = $file->getClientOriginalName();
                    $media->mime_type = $file->getMimeType();
                    $media->extension = $file->getClientOriginalExtension();
                    $media->size = $file->getSize();
                    $media->type = 'image';
                    $media->category = 'product_images';
                    $media->disk = 'public';
                    $media->path = $path . $file->getClientOriginalName();
                    $media->mediable_id = $product->id;
                    $media->mediable_type = products::class;
                    if ($request->has('caption')) {
                        $media->caption = $request->input('caption');
                    }

                    $media->user_id = auth()->id();
                    $media->save();
                    $file->move(public_path($path), $file->getClientOriginalName());

                }


            }
            if ($request->hasFile('sizechart')) {

                // Store file in 'public/media'
                $images = $request->file('images');
                $path = 'storage/images/products/' . $product->id . '/';
                if (!file_exists(public_path($path))) {
                    mkdir(public_path($path), 0777, true);
                }

                    // Save in media table
                    $media = new Media();
                    $media->filename = basename($file->getClientOriginalName());
                    $media->original_name = $file->getClientOriginalName();
                    $media->mime_type = $file->getMimeType();
                    $media->extension = $file->getClientOriginalExtension();
                    $media->size = $file->getSize();
                    $media->type = 'image';
                    $media->category = 'sizechart';
                    $media->disk = 'public';
                    $media->path = $path . $file->getClientOriginalName();
                    $media->mediable_id = $product->id;
                    $media->mediable_type = products::class;
                    if ($request->has('caption')) {
                        $media->caption = $request->input('caption');
                    }

                    $media->user_id = auth()->id();
                    $media->save();
                    $file->move(public_path($path), $file->getClientOriginalName());



            }
            if ($request->has('categories')) {
                $product->categories()->attach($request->categories);

            }
            if ($request->has('segments')) {
                $product->segments()->attach($request->segments);
            }
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());

        }
        return redirect()->route('admin.products')->with('status', 'Product Added Successfully');
    }

    public function generateProductThumbnailImage($image, $imageName)
    {
        $thumbnail_path = public_path('storage/images/products/thumbnails/');
        $image_path = public_path('storage/images/products/');

        $image = Image::read($image->path());
        $image->save($image_path . $imageName, 70);
        $image->save($thumbnail_path . $imageName, 70);

    }

    public function productEdit($id)
    {
        $product = products::find($id);
        return view('admin.products-edit', compact('product', ));
    }
    public function productUpdate(Request $request)
    {
        // return $request->all();
        $request->validate([
            'name' => 'required',
            'price' => 'required|numeric',
            'stock_status' => 'required|in:in_stock,out_of_stock',
            'featured' => 'boolean',
            'quantity' => 'required|integer',
            'image' => 'mimes:jpg,jpeg,png,webp|max:2048',

        ]);
        $product = products::find($request->id);
        $product->name = $request->name;
        $product->price = $request->price;
        if ($request->slug) {
            $slug = $request->slug;
            if (products::where('slug', $slug)->whereNotIn('id', [$product->id])->exists()) {
                $slug = $slug . '-' . Carbon::now()->timestamp;
            }
            $product->slug = $slug;
        }


        $product->discount_price = $request->discount_price;

        if ($request->sku) {
            $product->sku = $request->sku;
        }
        $product->stock_status = $request->stock_status;
        $product->featured = $request->featured ? true : false;
        $product->quantity = $request->quantity;
        if ($request->description) {
            $product->description = $request->description;
        }
        if ($request->hasFile('image')) {
            if (File::exists(public_path('storage/images/products/thumbnails/' . $product->image))) {
                File::delete(public_path('storage/images/products/thumbnails/' . $product->image));
                File::delete(public_path('storage/images/products/' . $product->image));
            }
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp . "." . $extension;
            $this->generateProductThumbnailImage($image, $filename);
            $product->image = $filename;
        }


        if ($request->short_description) {
            $product->short_description = $request->short_description;
        }
        if ($request->has('status')) {
            $product->status = true;
        } else {
            $product->status = false;
        }

        $product->save();
        $product->sizes()->delete();
        if ($request->has('sizes')) {

            foreach ($request->sizes as $key => $size) {
                $size = Size::create([
                    'products_id' => $product->id,
                    'name' => $size
                ]);
            }
        }
        if ($request->hasFile('images')) {

            // Store file in 'public/media'
            $images = $request->file('images');
            $path = 'storage/images/products/' . $product->id . '/';
            if (!file_exists(public_path($path))) {
                mkdir(public_path($path), 0777, true);
            }
            $old_media = $product->media()->where('category', 'product_images')->get();
            foreach ($old_media as $media) {
                if (file_exists(public_path($media->path))) {
                    unlink(public_path($media->path));
                }
                $media->delete();
            }

            foreach ($images as $key => $file) {


                // Save in media table
                $media = new Media();
                $media->filename = basename($file->getClientOriginalName());
                $media->original_name = $file->getClientOriginalName();
                $media->mime_type = $file->getMimeType();
                $media->extension = $file->getClientOriginalExtension();
                $media->size = $file->getSize();
                $media->type = 'image';
                $media->category = 'product_images';
                $media->disk = 'public';
                $media->path = $path . $file->getClientOriginalName();
                $media->mediable_id = $product->id;
                $media->mediable_type = products::class;
                if ($request->has('caption')) {
                    $media->caption = $request->input('caption');
                }

                $media->user_id = auth()->id();
                $media->save();
                $file->move(public_path($path), $file->getClientOriginalName());

            }


        }
        if ($request->hasFile('sizechart')) {

            // Store file in 'public/media'
            $images = $request->file('sizechart');
            $path = 'storage/images/products/' . $product->id . '/';
            if (!file_exists(public_path($path))) {
                mkdir(public_path($path), 0777, true);
            }
            $old_media = $product->sizeChart;
            if ($old_media) {
                $media = $old_media;

                if (file_exists(public_path($media->path))) {
                    unlink(public_path($media->path));
                }
                $media->delete();
            }


                $file = $images;
                // Save in media table
                $media = new Media();
                $media->filename = basename($file->getClientOriginalName());
                $media->original_name = $file->getClientOriginalName();
                $media->mime_type = $file->getMimeType();
                $media->extension = $file->getClientOriginalExtension();
                $media->size = $file->getSize();
                $media->type = 'image';
                $media->category = 'sizechart';
                $media->disk = 'public';
                $media->path = $path . $file->getClientOriginalName();
                $media->mediable_id = $product->id;
                $media->mediable_type = products::class;
                if ($request->has('caption')) {
                    $media->caption = $request->input('caption');
                }

                $media->user_id = auth()->id();
                $media->save();
                $file->move(public_path($path), $file->getClientOriginalName());
        }
        if ($request->has('categories')) {
            $product->categories()->sync($request->categories);
        }
        if ($request->has('segments')) {
            $product->segments()->sync($request->segments);
        }

        return redirect()->route('admin.products')->with('status', 'Product Updated Successfully');
    }


    public function productDelete($id)
    {
        $product = products::find($id);
        if (File::exists(public_path('storage/images/products/thumbnails/' . $product->image))) {
            File::delete(public_path('storage/images/products/thumbnails/' . $product->image));
            File::delete(public_path('storage/images/products/' . $product->image));

        }
        $product->delete();
        return redirect()->route('admin.products')->with('status', 'Product Deleted Successfully');
    }
    public function coupons()
    {
        $coupons = Coupon::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.coupons', compact('coupons'));
    }
    public function couponAdd()
    {
        return view('admin.coupons-add');
    }
    public function couponStore(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric',
            'cart_value' => 'required|numeric',
            'expiry_date' => 'required|date_format:Y-m-d',
        ]);
        $coupon = new Coupon();
        $coupon->code = $request->code;
        $coupon->type = $request->type;
        $coupon->value = $request->value;
        $coupon->cart_value = $request->cart_value;
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();
        return redirect()->route('admin.coupons')->with('status', 'Coupon Added Successfully');
    }
    public function couponEdit($id)
    {
        $coupon = Coupon::find($id);
        return view('admin.coupons-edit', compact('coupon'));
    }
    public function couponUpdate(Request $request)
    {
        $this->validate($request, [
            'code' => 'required',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric',
            'cart_value' => 'required|numeric',
            'expiry_date' => 'required|date_format:Y-m-d',
        ]);
        $coupon = Coupon::find($request->id);
        $coupon->code = $request->code;
        $coupon->type = $request->type;
        $coupon->value = $request->value;
        $coupon->cart_value = $request->cart_value;
        $coupon->expiry_date = $request->expiry_date;
        $coupon->save();
        return redirect()->route('admin.coupons')->with('status', 'Coupon Updated Successfully');
    }
    public function couponDelete($id)
    {
        $coupon = Coupon::find($id);
        $coupon->delete();
        return redirect()->route('admin.coupons')->with('status', 'Coupon Deleted Successfully');
    }
    public function orders(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')
                ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::whereNot('status', 'deleted')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::whereNot('status', 'deleted')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersPending(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'pending')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'pending')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'pending')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-pending', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersConfirmed(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'confirmed')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'confirmed')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'confirmed')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-confirmed', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersProcessing(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'processing')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                  ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'processing')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'processing')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-processing', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersReady(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'ready')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                 ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'ready')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'ready')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-ready', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersInReview(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'in_review')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                    ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'in_review')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'in_review')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-in_review', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersInTransit(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'in_transit')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                 ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'in_transit')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'in_transit')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-in_transit', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersDelivered(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'delivered')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                  ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'delivered')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'delivered')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-delivered', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersDeliveryInReview(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'delivery_in_review')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                 ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'delivery_in_review')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'delivery_in_review')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-delivery_in_review', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersOnHold(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'on_hold')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                   ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'on_hold')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'on_hold')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-on_hold', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersCancelled(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'cancelled')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                   ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'cancelled')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'cancelled')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-cancelled', compact('orders', 'status_group', 'orders_count'));
    }
    public function ordersReturned(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::whereNot('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('status', 'returned')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

        } elseif ($request->has('order_status')) {
            $status = $request->order_status;
            $orders = Order::where('status', 'returned')->where('status', $status)->orderBy('created_at', 'desc')->paginate(20);
        } else {
            $orders = Order::where('status', 'returned')->orderBy('created_at', 'desc')->paginate(20);

        }

        $status_group = Order::whereNot('status', 'deleted')->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();

        return view('admin.orders-returned', compact('orders', 'status_group', 'orders_count'));
    }
    public function deletedOrders(Request $request)
    {
        if ($request->has('search')) {
            $search = $request->search;
            $orders = Order::where('status', 'deleted')->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('phone', 'LIKE', '%' . $search . '%')

                  ->orWhere('id', 'LIKE', '%' . $search . '%')
                ->orWhere('consignment_id', 'LIKE', '%' . $search . '%')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $orders = Order::where('status', 'deleted')->orderBy('created_at', 'desc')->paginate(20);
        }
        $status_group = Order::select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();
        $orders_count = Order::count();
        return view('admin.deleted-orders', compact('orders', 'status_group', 'orders_count'));
    }

    public function orderDetails($id)
    {
        $order = Order::find($id);
        if (!$order) {
            abort(404);
        }
        if ($order) {

            //fraud check steadfst
            $phone = $order->phone;
            if (strlen($phone) == 11) {

                try {
                    $order->fraud_check_steadfast = collect((new SteadfastService())->steadfast($phone));
                } catch (\Throwable $e) {
                    $order->fraud_check_steadfast = collect(['error' => 'Steadfast check failed']);
                }

                $order->fraud_check_pathao = collect((new PathaoService())->pathao($phone));
                // dd($order->fraud_check_pathao);
            }
        }
        // dd($order->fraud_check);
        $orderItems = Order_Item::where('order_id', $id)->paginate(20);
        $products = products::all();
        return view('admin.order-details', compact('order', 'orderItems', 'products'))->with('title', 'Order Details');
    }
    public function orderStatusUpdate(Request $request)
    {


        $order = Order::find($request->order_id);
        if ($order->status == $request->status) {

            return redirect()->route('admin.orders.details', $order->id)->with('status', 'Order Status Already Updated');

        } else {
            if ($request->status == 'pending') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'on_hold') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'confirmed') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'processing') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'in_transit') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'delivered') {
                $order->status = $request->status;
                $order->delivery_date = Carbon::now();
                $order->is_paid = true;
                $order->save();
            }

            if ($request->status == 'cancelled') {
                $order->status = $request->status;
                $order->cancelled_date = Carbon::now();
                $order->save();
            }

            if ($request->status == 'returned') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'deleted') {
                $order->status = $request->status;
                $order->save();
            }



        }





        return redirect()->route('admin.orders.details', $order->id)->with('status', 'Order Status Updated Successfully');
    }

    public function ordersoftdelete($id)
    {
        $order = Order::find($id);
        if ($order->status == 'deleted') {
            return redirect()->back()->with('status', 'Order Already Deleted');
        }
        $order->status = 'deleted';
        $order->save();
        return redirect()->back()->with('status', 'Order Deleted Successfully');
    }
    public function deleteOrder($id)
    {
        $order = Order::find($id);
        $orderItems = Order_Item::where('order_id', $id)->get();
        foreach ($orderItems as $orderItem) {
            $orderItem->delete();
        }
        $order->delete();
        return redirect()->back()->with('status', 'Order Deleted Successfully');
    }
    public function exportOrders(Request $request)
    {
        $order_status = $request->order_status ?? null;
        if ($request->has('order_status')) {

            $fileName = $order_status . '_orders_' . Carbon::now()->format('Y_m_d_H_i_s') . '.xlsx';
        } else {
            $fileName = 'orders_' . Carbon::now()->format('Y_m_d_H_i_s') . '.xlsx';
        }

        return Excel::download(new OrderExport($order_status), $fileName);
    }
    public function updateOrder(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return redirect()->back()->with('error', 'Order not found');
        }
        $order->Order_Item()->delete();
        // $order->Order_Item()
        $orderedProducts = products::whereIn('id', $request->products)->get();
        foreach ($orderedProducts as $orderedProduct) {
            $orderItem = new Order_Item();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $orderedProduct->id;
            $orderItem->quantity = $request->order_items[$orderedProduct->id]['quantity'];
            $orderItem->price = $orderedProduct->price;
            $orderItem->options = json_encode(['size' => $request->order_items[$orderedProduct->id]['size']]);
            $orderItem->save();
        }
        $subtotal = $orderedProducts->sum(function ($product) use ($request) {
            return (float) ($product->discount_price ?? $product->price) * (float) $request->order_items[$product->id]['quantity'];
        });
        $order->subtotal = $subtotal;
        $order->discount = $request->discount;
        $order->fee = $request->delivery_charge;
        $order->total = ($subtotal + (float) $request->delivery_charge) - (float) $request->discount;

        $order->save();



        $order->save();

        // return $request->all();
        return redirect()->back()->with('status', 'Order Updated Successfully');
    }
    public function updateOrderDetails(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return redirect()->back()->with('error', 'Order not found');
        }
        $order->name = $request->name;
        $order->phone = $request->phone;
        $order->address = $request->address;
        $order->note = $request->note;
        $order->save();
        return redirect()->back()->with('status', 'Order Details Updated Successfully');
    }
    public function deliveryAreas()
    {
        $deliveryAreas = delivery_areas::orderBy('id', 'desc')->paginate(10);
        return view('admin.delivery-areas', compact('deliveryAreas'));
    }
    public function deliveryAreaAdd()
    {
        return view('admin.delivery-areas-add');
    }
    public function deliveryAreaStore(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'charge' => 'required|numeric',
        ]);
        $deliveryArea = new delivery_areas();
        $deliveryArea->name = $request->name;
        $deliveryArea->charge = $request->charge;
        $deliveryArea->save();
        return redirect()->route('admin.deliveryareas')->with('status', 'Delivery Area Added Successfully');
    }
    public function deliveryAreaEdit($id)
    {
        $deliveryArea = delivery_areas::find($id);
        return view('admin.delivery-areas-edit', compact('deliveryArea'));
    }
    public function deliveryAreaUpdate(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'charge' => 'required|numeric',
        ]);
        $deliveryArea = delivery_areas::find($request->id);
        $deliveryArea->name = $request->name;
        $deliveryArea->charge = $request->charge;
        $deliveryArea->save();
        return redirect()->route('admin.deliveryareas')->with('status', 'Delivery Area Updated Successfully');
    }

    public function deliveryAreaDelete($id)
    {
        $deliveryArea = delivery_areas::find($id);
        $deliveryArea->delete();
        return redirect()->route('admin.deliveryareas')->with('status', 'Delivery Area Deleted Successfully');
    }

    //Slides
    public function slides()
    {
        $slides = Slide::orderBy('id', 'desc')->paginate(10);
        return view('admin.slides', compact('slides'));
    }
    public function slideAdd()
    {
        return view('admin.slides-add');
    }
    public function slideStore(Request $request)
    {

        $this->validate($request, [
            'title' => 'required',
            'subtitle' => 'required',
            'tagline' => 'required',
            'image' => 'required|mimes:jpg,jpeg,png',
        ]);

        $slide = new Slide();
        $slide->title = $request->title;
        $slide->subtitle = $request->subtitle;
        $slide->tagline = $request->tagline;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp . "." . $extension;
            $this->GenerateSlideThumbnailImage($image, $filename);
            $slide->image = $filename;
        }
        $slide->save();
        return redirect()->route('admin.slides')->with('status', 'Slide Added Successfully');
    }

    public function GenerateSlideThumbnailImage($image, $imageName)
    {
        $thumbnail_path = public_path('storage/images/slides/thumbnails/');
        $image_path = public_path('storage/images/slides/');
        $image = Image::read($image->path());
        $image->cover(602, 602, 'top');
        $image->resize(602, 602, function ($constraint) {
            $constraint->aspectRatio();
        });
        $image->save($image_path . $imageName);
        $image->cover(202, 202, 'top');
        $image->resize(202, 202, function ($constraint) {
            $constraint->aspectRatio();
        });
        $image->save($thumbnail_path . $imageName);
    }
    public function slideEdit($id)
    {
        $slide = Slide::find($id);
        return view('admin.slides-edit', compact('slide'));
    }
    public function slideUpdate(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'subtitle' => 'required',
            'tagline' => 'required',
        ]);
        $slide = Slide::find($request->id);
        $slide->title = $request->title;
        $slide->subtitle = $request->subtitle;
        $slide->tagline = $request->tagline;
        if ($request->hasFile('image')) {
            if (File::exists(public_path('storage/images/slides/thumbnails/' . $slide->image))) {
                File::delete(public_path('storage/images/slides/thumbnails/' . $slide->image));
                File::delete(public_path('storage/images/slides/' . $slide->image));
            }
            $image = $request->file('image');
            $extension = $image->getClientOriginalExtension();
            $filename = Carbon::now()->timestamp . "." . $extension;
            $this->GenerateSlideThumbnailImage($image, $filename);
            $slide->image = $filename;
        }
        $slide->save();
        return redirect()->route('admin.slides')->with('status', 'Slide Updated Successfully');
    }
    public function slideDelete($id)
    {
        $slide = Slide::find($id);
        if (File::exists(public_path('storage/images/slides/thumbnails/' . $slide->image))) {
            File::delete(public_path('storage/images/slides/thumbnails/' . $slide->image));
            File::delete(public_path('storage/images/slides/' . $slide->image));
        }
        $slide->delete();
        return redirect()->route('admin.slides')->with('status', 'Slide Deleted Successfully');
    }
    //Analytics
    public function gAnalaytics()
    {
        $google_analytics = Analytic::where('slug', 'google-analytics')->first();
        return view('admin.google-analytics', compact('google_analytics'));
    }
    public function gAnalyticsUpdate(Request $request)
    {

        $google_analytics = Analytic::find($request->id);
        $google_analytics->code = $request->code ?? '';
        $google_analytics->save();
        return redirect()->route('admin.google.analytics')->with('status', 'Google Analytics Code Updated Successfully');
    }
    public function fbPixels()
    {
        $facebook_pixels = Analytic::where('slug', 'facebook-pixels')->first();
        return view('admin.facebook-pixels', compact('facebook_pixels'));
    }
    public function fbPixelsUpdate(Request $request)
    {
        $facebook_pixels = Analytic::find($request->id);
        $facebook_pixels->code = $request->code ?? '';
        $facebook_pixels->save();
        return redirect()->route('admin.facebook.pixels')->with('status', 'Facebook Pixels Code Updated Successfully');
    }






    public function analytics()
    {
        // Total stats
        $totalVisitors = Visit::distinct('ip_address')->count('ip_address');
        $totalPageViews = Visit::count();

        $todayVisitors = Visit::whereDate('created_at', today())
            ->distinct('ip_address')->count('ip_address');
        $todayPageViews = Visit::whereDate('created_at', today())->count();

        // Device stats
        $devices = Visit::select('device')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('device')
            ->get();

        // Referrers stats
        $referrers = Visit::select('referrer')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('referrer')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // Pages stats
        $pages = Visit::select('page_url')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('page_url')
            ->orderByDesc('total')
            ->take(10)
            ->get();
        // Pages stats
        $todaypages = Visit::whereDate('created_at', today())
            ->select('page_url')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('page_url')
            ->orderByDesc('total')
            ->get();

        // Locations stats
        $locations = Visit::select('country')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('country')
            ->orderByDesc('total')
            ->get();
        // cities
        $cities = Visit::select('city')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('city')
            ->orderByDesc('total')
            ->get();

        return view('admin.analytics', compact(
            'totalVisitors',
            'totalPageViews',
            'todayVisitors',
            'todayPageViews',
            'devices',
            'referrers',
            'pages',
            'locations',
            'cities',
            'todaypages',
        ));
    }
    public function orderAdd()
    {
        $products = products::all();
        $delivery_areas = delivery_areas::all();

        return view('admin.order-add', compact('products', 'delivery_areas'));
    }
    public function orderStore(Request $request)
    {
        $order = new Order();
        $order->name = $request->name;
        $order->phone = $request->phone;
        $order->address = $request->address;
        $order->note = $request->note;
        $order->save();
        return redirect()->route('admin.orders')->with('status', 'Order Created Successfully');
    }

    public function bulkOrderStatusUpdate(Request $request)
    {




        foreach ($request->ids as $id) {
            $order = Order::find($id);

            if ($request->status == 'pending') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'on_hold') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'confirmed') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'processing') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'in_transit') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'delivered') {
                $order->status = $request->status;
                $order->delivery_date = Carbon::now();
                $order->save();
            }

            if ($request->status == 'cancelled') {
                $order->status = $request->status;
                $order->cancelled_date = Carbon::now();
                $order->save();
            }

            if ($request->status == 'returned') {
                $order->status = $request->status;
                $order->save();
            }
            if ($request->status == 'deleted') {
                $order->status = $request->status;
                $order->save();
            }
        }
        return response()->json(['success' => 'Order Status Updated Successfully']);
    }
    public function orderBlacklistUpdate($id)
    {
        $order = Order::find($id);
        $customer = Customer::where('phone', $order->phone)->first();
        $device = Device::where('user_agent', $order->user_agent)->first();


        if (!$customer) {
            $customer = new Customer();
            $customer->name = $order->name;
            $customer->phone = $order->phone;
            $customer->save();
            $customer->blackLists()->create([
                'reason' => 'Blacklist from Order by ' . auth()->user()->name,
            ]);

        } elseif ($customer->isBlocked == 0) {
            $customer->blackLists()->create([
                'reason' => 'Blacklist from Order by ' . auth()->user()->name,
            ]);
        }

        if (!$device) {
            $device = new Device();
            $device->user_agent = $order->user_agent;
            $device->customer_id = $customer->id;
            $device->save();
            $device->blackLists()->create([
                'reason' => 'Blacklist from Order by ' . auth()->user()->name,
            ]);
        } elseif ($device->isBlocked == 0) {
            $device->blackLists()->create([
                'reason' => 'Blacklist from Order by ' . auth()->user()->name,
            ]);
        }


        return response()->json([
            'success' => true,
            'message' => 'Order Blacklisted Successfully',
            'customer' => $customer,
            'device' => $device,
            'DeviceisBlocked' => $device->isBlocked,
            'CustomerisBlocked' => $customer->isBlocked,
        ]);
    }
    public function unblockOrder($id)
    {
        $order = Order::find($id);
        $customer = Customer::where('phone', $order->phone)->first();
        $device = Device::where('user_agent', $order->user_agent)->first();
        if($customer && $device){

        $customer->blackLists()->delete();
        $device->blackLists()->delete();
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Customer or Device is not Blacklisted',
            ]);
        }



        return response()->json([
            'success' => true,
            'message' => 'Order Unblocked Successfully',
            'customer' => $customer,
            'device' => $device,
            'DeviceisBlocked' => $device->isBlocked,
            'CustomerisBlocked' => $customer->isBlocked,
        ]);
    }
}
