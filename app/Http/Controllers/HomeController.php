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

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars(config('app.name')));
        $channel->addChild('link', htmlspecialchars(url('/')));
        $channel->addChild('description', 'Facebook product catalog feed');

        foreach ($products as $product) {
            $hasDiscount = $product->discount_price && $product->discount_price > 0;

            $item = $channel->addChild('item');
            $item->addChild('g:id', $product->id, 'http://base.google.com/ns/1.0');
            $item->addChild('g:title', null, 'http://base.google.com/ns/1.0')[0] = $product->name;
            $item->addChild('g:description', null, 'http://base.google.com/ns/1.0')[0] =
                strip_tags((string) $product->short_description ?: (string) $product->description);
            $item->addChild('g:availability', $product->stock_status === 'in_stock' ? 'in stock' : 'out of stock', 'http://base.google.com/ns/1.0');
            $item->addChild('g:condition', 'new', 'http://base.google.com/ns/1.0');
            $item->addChild('g:price', number_format((float) $product->price, 2, '.', '') . ' BDT', 'http://base.google.com/ns/1.0');
            if ($hasDiscount) {
                $item->addChild('g:sale_price', number_format((float) $product->discount_price, 2, '.', '') . ' BDT', 'http://base.google.com/ns/1.0');
            }
            $item->addChild('g:link', null, 'http://base.google.com/ns/1.0')[0] = route('product.show', $product->slug);
            $item->addChild('g:image_link', null, 'http://base.google.com/ns/1.0')[0] = asset('storage/images/products/' . $product->image);
            $item->addChild('g:brand', htmlspecialchars(config('app.name')), 'http://base.google.com/ns/1.0');
        }

        return response($xml->asXML(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
