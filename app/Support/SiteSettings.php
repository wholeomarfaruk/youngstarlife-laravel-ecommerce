<?php

namespace App\Support;

use App\Models\Ops\Setting;

/**
 * Site settings (Ops → Settings / Storefront): every group stored as JSON in ops_settings, merged over OpsDefaults.
 * One cache entry holds all groups (refreshed on every Setting::put / flush) and merged groups are memoised per request,
 * so the storefront can read dozens of settings per page for one cache read.
 *
 *   site('branding.store_name')        dotted read with defaults
 *   site()->whatsapp()                 helpers for values the website shows in many places
 *   site()->text('content.order_failed') text with {whatsapp} {store} {phone} placeholders filled in
 */
class SiteSettings
{
    public const CACHE_KEY = 'site_settings';

    protected ?array $stored = null;   // raw stored groups
    protected array $merged = [];      // group => defaults + stored

    public function get(string $key, $default = null)
    {
        [$group, $path] = array_pad(explode('.', $key, 2), 2, null);
        $value = $this->group($group);
        return $path === null ? ($value ?? $default) : data_get($value, $path, $default);
    }

    public function group(string $group)
    {
        if (array_key_exists($group, $this->merged)) return $this->merged[$group];
        $defaults = OpsDefaults::get($group);
        $stored = $this->stored()[$group] ?? null;
        return $this->merged[$group] = $defaults !== null ? OpsDefaults::merge($defaults, $stored) : $stored;
    }

    /** raw stored value of one group (no defaults) — what Setting::get returns */
    public function raw(string $group, $default = null) { return $this->stored()[$group] ?? $default; }

    public function stored(): array
    {
        return $this->stored ??= cache()->rememberForever(self::CACHE_KEY, fn () => Setting::query()->get()->mapWithKeys(fn ($s) => [$s->key => $s->value])->all());
    }

    /** after a save: drop the cache and this request's memo */
    public function flush(): void
    {
        cache()->forget(self::CACHE_KEY);
        $this->stored = null; $this->merged = [];
    }

    // ── values the website uses in many places

    /** Settings → Orders & delivery → Website cart: off = no cart icon / drawer / add to cart, customers order from the product page form */
    public function cartEnabled(): bool { return (bool) $this->get('orders.cart_enabled', true); }

    public function storeName(): string { return (string) ($this->get('branding.store_name') ?: 'YOUNGSTAR Life'); }

    public function logoUrl(): string { return $this->media($this->get('branding.logo')) ?? asset('frontend/img/youngstar logo-circle.png'); }

    public function faviconUrl(): string { return $this->media($this->get('branding.favicon')) ?? $this->logoUrl(); }

    public function shareImageUrl(): string
    {
        return ($this->get('seo.share_image') === 'custom' ? $this->media($this->get('seo.share_image_path')) : null) ?? $this->logoUrl();
    }

    /** 01XXXXXXXXX (Storefront → Social & chat) */
    public function whatsapp(): string
    {
        $raw = (string) $this->get('storefront.social.whatsapp');
        return Phone::normalize($raw) ?? (preg_replace('/\D/', '', $raw) ?: '');
    }

    public function whatsappLink(?string $text = null): ?string
    {
        if (! $n = $this->whatsapp()) return null;
        return 'https://wa.me/'.(str_starts_with($n, '0') ? '88'.$n : $n).($text ? '?text='.rawurlencode($text) : '');
    }

    /** a text setting with {store} {whatsapp} {phone} {email} filled in */
    public function text(string $key, array $vars = []): string
    {
        return strtr((string) $this->get($key, ''), $vars + [
            '{store}' => $this->storeName(), '{whatsapp}' => $this->whatsapp(),
            '{phone}' => (string) $this->get('company.phone'), '{email}' => (string) $this->get('company.email'),
        ]);
    }

    /** "{page} · {site}" (SEO → title format) */
    public function title(?string $page = null): string
    {
        $site = (string) ($this->get('seo.title') ?: $this->storeName());
        if (! $page) return $site;
        return strtr((string) ($this->get('seo.title_format') ?: '{page} · {site}'), ['{page}' => $page, '{site}' => $this->storeName()]);
    }

    protected function media(?string $path): ?string
    {
        if (! $path) return null;
        return preg_match('#^(https?:)?//#', $path) ? $path : asset('storage/'.ltrim($path, '/'));
    }
}
