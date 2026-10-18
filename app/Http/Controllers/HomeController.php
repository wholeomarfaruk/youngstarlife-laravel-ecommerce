<?php

namespace App\Http\Controllers;

use App\Models\Ops\{Category, DeliveryZone, Product};
use App\Models\Slide;

/** Public website pages — catalogue from the Ops system (ops_products / ops_categories / ops_delivery_zones). */
class HomeController extends Controller
{
    /** storefront listing order: Ops "sort order" (lower first), newest first */
    protected function listed()
    {
        return Product::storefront()->orderBy('sort_order')->orderByDesc('id');
    }

    public function index()
    {
        $products = $this->listed()->paginate(12);
        $categories = Category::orderBy('sort')->orderBy('name')
            ->with(['products' => fn ($q) => $q->storefront()->orderByPivot('sort_order')->orderByDesc('ops_products.id')])->get()
            ->filter(fn ($c) => $c->products->isNotEmpty());

        $reviewsQuery = Slide::where('status', 1);   // customer review screenshots (managed in the old admin until moved)
        $total = (clone $reviewsQuery)->count();
        $reviews = $reviewsQuery->orderByDesc('id')->take(20)->get();

        return view('home-one', compact('products', 'categories', 'reviews', 'total'));
    }

    public function reviews()
    {
        $reviews = Slide::where('status', 1)->orderByDesc('id')->get();
        return view('reviews', compact('reviews'));
    }

    public function shop(\Illuminate\Http\Request $request)
    {
        $q = $this->query($request);
        $products = $this->searched($this->listed(), $q)->paginate(12)->withQueryString();
        return view('shop', compact('products', 'q'));
    }

    /** header search modal: live results while typing (JSON, cached briefly per query) */
    public function searchSuggest(\Illuminate\Http\Request $request)
    {
        $q = $this->query($request);
        if (mb_strlen($q) < 2) return response()->json(['items' => [], 'total' => 0]);
        $data = cache()->remember('search:'.md5(mb_strtolower($q)), 60, function () use ($q) {
            $base = $this->searched($this->listed(), $q);
            return ['total' => (clone $base)->count(), 'items' => $base->limit(8)->get(['id', 'name', 'slug', 'code', 'price', 'sale_price', 'featured_image'])
                ->map(fn ($p) => ['name' => $p->name, 'url' => route('product.show', $p->slug, false), 'image' => $p->thumb_url, 'price' => $p->sellPrice(), 'old' => $p->hasDiscount() ? (int) $p->price : 0])->all()];
        });
        return response()->json($data + ['all' => route('shop', ['q' => $q], false)]);
    }

    protected function query(\Illuminate\Http\Request $request): string { return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $request->query('q'))), 0, 60); }

    /** every word must hit the name, code or a category name ("cargo combo" finds "… Cargo Pant Combo") */
    protected function searched($query, string $q)
    {
        foreach (array_slice(array_filter(explode(' ', $q), fn ($w) => mb_strlen($w) > 0), 0, 6) as $w) {
            $like = '%'.addcslashes($w, '%_\\').'%';   // a typed % or _ is a letter, not a wildcard
            $query->where(fn ($x) => $x->where('ops_products.name', 'like', $like)->orWhere('ops_products.code', 'like', $like)
                ->orWhereHas('categories', fn ($c) => $c->where('ops_categories.name', 'like', $like)));
        }
        return $query;
    }

    public function productShow(string $slug)
    {
        $product = Product::storefront()->where('slug', $slug)->with(['variants' => fn ($q) => $q->orderBy('id'), 'comboItems.variant.product.variants'])->first();
        if (! $product) abort(404);

        // below-the-fold blocks: cached (the product section itself is cached in ProductPage); "more products" shuffled in PHP, not ORDER BY RAND()
        $products = cache()->remember('pp:more', 600, fn () => Product::storefront()->orderByDesc('id')->limit(40)->get(['id', 'name', 'slug', 'price', 'sale_price', 'featured_image']))
            ->where('id', '!=', $product->id)->shuffle()->take(8)->values();
        [$total, $reviews] = cache()->remember('pp:reviews', 600, fn () => [Slide::where('status', 1)->count(), Slide::where('status', 1)->orderByDesc('id')->take(20)->get()]);

        return view('product-show', compact('product', 'products', 'reviews', 'total'));
    }

    public function categoryShow(string $slug)
    {
        $category = Category::where('slug', $slug)->first();
        if (! $category) abort(404);
        // per-category order (Ops), then the global order
        $products = $category->products()->storefront()->orderByPivot('sort_order')->orderBy('sort_order')->orderByDesc('ops_products.id')->paginate(12);
        return view('category-products', compact('category', 'products'));
    }

    /** Settings → SEO → sitemap.xml (off → 404) */
    public function sitemap()
    {
        abort_unless(site('seo.sitemap', true) && site('seo.indexable', true), 404);
        $xml = cache()->remember('sitemap.xml', 3600, function () {
            $urls = collect([[route('home'), now()], [route('shop'), null], [route('reviews'), null]])
                ->concat(Category::orderBy('sort')->get(['slug', 'updated_at'])->map(fn ($c) => [route('category.show', $c->slug), $c->updated_at]))
                ->concat(Product::storefront()->orderBy('id')->get(['id', 'slug', 'updated_at'])->map(fn ($p) => [route('product.show', $p->slug), $p->updated_at]));
            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .$urls->map(fn ($u) => '<url><loc>'.e($u[0]).'</loc>'.($u[1] ? '<lastmod>'.$u[1]->toAtomString().'</lastmod>' : '').'</url>')->join('')
                .'</urlset>';
        });
        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function facebookProductFeed()
    {
        $products = Product::storefront()->orderBy('id')->get();
        $g = 'http://base.google.com/ns/1.0';
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss xmlns:g="'.$g.'" version="2.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars(site()->storeName()));
        $channel->addChild('link', htmlspecialchars(url('/')));
        $channel->addChild('description', 'Facebook product catalog feed');

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', $product->catalogId(), $g);   // old website id for imported products → catalog + pixel stay matched
            $item->addChild('g:title', null, $g)[0] = $product->name;
            $item->addChild('g:description', null, $g)[0] = strip_tags((string) $product->short_description ?: (string) $product->description);
            $item->addChild('g:availability', $product->isOrderable() ? 'in stock' : 'out of stock', $g);
            $item->addChild('g:condition', 'new', $g);
            $item->addChild('g:price', number_format((float) $product->price, 2, '.', '').' BDT', $g);
            if ($product->hasDiscount()) $item->addChild('g:sale_price', number_format((float) $product->sale_price, 2, '.', '').' BDT', $g);
            $item->addChild('g:link', null, $g)[0] = route('product.show', $product->slug);
            $item->addChild('g:image_link', null, $g)[0] = $product->image_url;
            $item->addChild('g:brand', htmlspecialchars(config('app.name')), $g);
        }

        return response($xml->asXML(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
