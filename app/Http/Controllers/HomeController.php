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


    public function productShow(Request $request, $slug)
    {
        $product = products::where('slug', $slug)->where('status', 1)->first();
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
