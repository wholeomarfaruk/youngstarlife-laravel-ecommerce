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

        $products = Products::where('status', 1)
            ->orderBy('sort_order', 'asc') // manual admin sort order
            ->orderByDesc('id')
            ->paginate(12);
        $deliveryAreas = delivery_areas::all();
        $slides = Slide::all();
        $analytics = Analytic::all();
        $categories = Category::all();

        $reviewsQuery = Slide::where('status', 1);
        $total = (clone $reviewsQuery)->count();
        $reviews = $reviewsQuery->orderByDesc('id')->take(20)->get();

        return view('home-one', compact('products', 'deliveryAreas', 'slides', 'analytics', 'categories', 'reviews', 'total'));
    }
    public function reviews()
    {
        $reviews = Slide::where('status', 1)->orderByDesc('id')->get();
        return view('reviews', compact('reviews'));
    }
    public function shop()
    {

        $products = Products::where('status', 1)
            ->orderBy('sort_order', 'asc') // manual admin sort order
            ->orderByDesc('id')
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

        $reviewsQuery = Slide::where('status', 1);
        $total = (clone $reviewsQuery)->count();
        $reviews = $reviewsQuery->orderByDesc('id')->take(20)->get();

        return view('product-show', compact('product', 'deliveryAreas', 'products', 'reviews', 'total'));
    }
    public function categoryShow(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->first();
        if (!$category) {
            abort(404);
        }

         // Order by the per-category sort order (product_category.sort_order)
         // set on the admin Manage Products page. No global product order here.
         $products = $category->products()
            ->where('status', 1)
            ->orderByPivot('sort_order', 'asc')
            ->paginate(12);
        return view('category-products', compact('category', 'products'));
    }

    public function facebookProductFeed()
    {
        $products = Products::where('status', 1)->orderBy('id')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="facebook-product-feed.csv"',
        ];

        $columns = [
            'id',
            'title',
            'description',
            'availability',
            'condition',
            'price',
            'sale_price',
            'link',
            'image_link',
            'brand',
        ];

        $callback = function () use ($products, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($products as $product) {
                $hasDiscount = $product->discount_price && $product->discount_price > 0;

                fputcsv($file, [
                    $product->id,
                    $product->name,
                    strip_tags((string) $product->short_description ?: (string) $product->description),
                    $product->stock_status === 'in_stock' ? 'in stock' : 'out of stock',
                    'new',
                    number_format((float) $product->price, 2, '.', '') . ' BDT',
                    $hasDiscount ? number_format((float) $product->discount_price, 2, '.', '') . ' BDT' : '',
                    route('product.show', $product->slug),
                    $product->featured_image,
                    config('app.name'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
