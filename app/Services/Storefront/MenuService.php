<?php

namespace App\Services\Storefront;

use App\Models\Ops\{Category, Product};
use App\Models\Storefront\{Menu, MenuItem};
use Illuminate\Support\Facades\Cache;

/**
 * Storefront menus: registry (config/storefront.php), URL resolution per item type, and a per-location cache that is
 * flushed whenever a menu, an item, a category or a product changes — so a page render costs one cache read per menu.
 */
class MenuService
{
    protected const CACHE = 'storefront.menu.';
    protected array $loaded = [];   // per-request memo

    public static function locations(): array { return config('storefront.menus', []); }

    public static function forget(?string $location = null): void
    {
        foreach ($location ? [$location] : array_keys(self::locations()) as $l) Cache::forget(self::CACHE.$l);
    }

    /** every registered location has a row (new locations added to the registry appear in the admin automatically) */
    public static function ensureRegistered(): void
    {
        foreach (self::locations() as $location => $def) Menu::firstOrCreate(['location' => $location], ['name' => $def['label']]);
    }

    /** @return array{title: ?string, items: array<int, array{label: string, url: string (relative for internal links), icon: ?string, new_tab: bool, external: bool, children: array}>} */
    public function get(string $location): array
    {
        return $this->loaded[$location] ??= Cache::rememberForever(self::CACHE.$location, fn () => $this->build($location));
    }

    /** active = the current path is the item's page (or a page under it), for internal links only */
    public function isActive(array $item): bool
    {
        if ($item['external']) return false;
        foreach ($item['children'] as $c) if ($this->isActive($c)) return true;
        $path = trim((string) parse_url($item['url'], PHP_URL_PATH), '/');
        return $path === '' ? request()->is('/') : request()->is($path, $path.'/*');
    }

    protected function build(string $location): array
    {
        $menu = Menu::where('location', $location)->first();
        if (! $menu) return ['title' => null, 'items' => []];
        $items = MenuItem::where('menu_id', $menu->id)->where('is_active', true)->orderBy('sort_order')->get();

        // one query per linked kind; deleted categories and hidden / deleted products drop out of the menu instead of 404-ing
        // (category pages don't check is_active on the storefront, so neither does the menu)
        $categories = Category::whereIn('id', $items->where('type', 'category')->pluck('ref_id'))->pluck('slug', 'id');
        $products = Product::whereIn('id', $items->where('type', 'product')->pluck('ref_id'))->where('status', 'active')->pluck('slug', 'id');

        $node = function (MenuItem $i) use ($categories, $products, $items, &$node) {
            $url = $this->url($i, $categories, $products);
            if ($url === null) return null;
            return ['label' => $i->label, 'url' => $url, 'icon' => $i->icon, 'new_tab' => $i->new_tab, 'external' => ! str_starts_with($url, '/'),
                'children' => $items->where('parent_id', $i->id)->map($node)->filter()->values()->all()];
        };
        return ['title' => $menu->title, 'items' => $items->whereNull('parent_id')->map($node)->filter()->values()->all()];
    }

    public function url(MenuItem $i, $categories = null, $products = null): ?string
    {
        return match ($i->type) {
            // internal links are relative: the cached menu is the same whatever host / scheme served the first request
            'route' => ($r = config('storefront.menu_routes.'.$i->value)) ? route($r[1], [], false) : null,
            'category' => ($slug = $categories ? ($categories[$i->ref_id] ?? null) : Category::whereKey($i->ref_id)->value('slug')) ? route('category.show', $slug, false) : null,
            'product' => ($slug = $products ? ($products[$i->ref_id] ?? null) : Product::whereKey($i->ref_id)->where('status', 'active')->value('slug')) ? route('product.show', $slug, false) : null,
            'whatsapp' => ($d = preg_replace('/\D/', '', (string) $i->value)) ? 'https://wa.me/'.$d : null,
            'phone' => ($n = preg_replace('/[^\d+]/', '', (string) $i->value)) ? 'tel:'.$n : null,
            'url' => $i->value ? (preg_match('#^(https?:|mailto:|tel:|/)#i', $i->value) ? $i->value : '/'.ltrim($i->value, '/')) : null,
            default => null,
        };
    }
}
