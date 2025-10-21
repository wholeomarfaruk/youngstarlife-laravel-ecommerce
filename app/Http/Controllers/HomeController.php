<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\products;
use App\Models\delivery_areas;
use App\Models\Slide;
use App\Models\Analytic;
use App\Models\Category;
use Illuminate\Support\Facades\Log;
class HomeController extends Controller
{

    public function index()
    {

        $products = Products::where('status', 1)->orderByDesc('featured') // featured first
            ->orderByDesc('created_at')               // newest first
            ->paginate(12);
        $deliveryAreas = delivery_areas::all();
        $slides = Slide::all();
        $analytics = Analytic::all();
        $categories = Category::all();
        return view('home-one', compact('products', 'deliveryAreas', 'slides', 'analytics', 'categories'));
    }
    public function shop()
    {

        $products = Products::where('status', 1)->orderByDesc('featured') // featured first
            ->orderByDesc('created_at')               // newest first
            ->paginate(12);
        $deliveryAreas = delivery_areas::all();
        $slides = Slide::all();
        $analytics = Analytic::all();
        $categories = Category::all();
        return view('shop', compact('products', 'deliveryAreas', 'slides', 'analytics', 'categories'));
    }

    public function ProductOne(Request $request)
    {
        $product = products::find(1);
        $deliveryAreas = delivery_areas::latest()->limit(5)->get();
        // DataLayer array prepare
        $dataLayer = [
            'event' => 'ViewContent',
            'content_name' => $product->name,
            'content_ids' => [$product->id],
            'content_type' => 'product',
            'value' => $product->price,
            'currency' => 'BDT'
        ];
        $checkoutData = [
            'event' => 'InitiateCheckout', // FB Pixel standard event
            'content_ids' => [$product->id],
            'content_name' => $product->name,
            'value' => $product->price,
            'currency' => 'BDT'
        ];
        return view('product-one', compact('product', 'deliveryAreas'));
    }
    public function ProductTwo(Request $request)
    {
        $product = products::find(2);
        $deliveryAreas = delivery_areas::latest()->limit(5)->get();
        return view('product-two', compact('product', 'deliveryAreas'));
    }
    public function ProductThree(Request $request)
    {
        $product = products::find(3);
        $deliveryAreas = delivery_areas::latest()->limit(5)->get();
        return view('product-three', compact('product', 'deliveryAreas'));
    }
    public function ProductTest(Request $request)
    {
        $product = products::find(4);
        $deliveryAreas = delivery_areas::latest()->limit(5)->get();
        $dataLayer = [
            'event' => 'ViewContent',
            'content_name' => $product->name,
            'content_ids' => [$product->id],
            'content_type' => 'product',
            'value' => $product->price,
            'currency' => 'BDT'
        ];
        $checkoutData = [
            'event' => 'InitiateCheckout', // FB Pixel standard event
            'content_ids' => [$product->id],
            'content_name' => $product->name,
            'value' => $product->price,
            'currency' => 'BDT'
        ];
        return view('product-test', compact('product', 'deliveryAreas', 'dataLayer', 'checkoutData'));
    }
    public function productShow(Request $request, $slug)
    {
        $product = products::where('slug', $slug)->first();
        if (!$product) {
            abort(404);
        }

        $deliveryAreas = delivery_areas::limit(5)->get();
        $products = products::where('status', 1)->where('id', '!=', $product->id)->inRandomOrder()->limit(8)->get();

        return view('product-show', compact('product', 'deliveryAreas', 'products'));
    }
    public function categoryShow(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->first();
        if (!$category) {
            abort(404);
        }

         $products = $category->products()->where('status', 1)->orderByDesc('featured') // featured first
            ->orderByDesc('created_at')               // newest first
            ->paginate(12);
        return view('category-products', compact('category', 'products'));
    }
}
