<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign attribution: which ad / UTM link brought the customer.
 *
 * CaptureAttribution middleware stores the landing parameters in two first-party cookies
 * (first touch is kept, last touch is overwritten on every new ad click), and forOrder()
 * turns them into order columns when the order is placed.
 *
 * Meta Ads -> Ad -> Tracking -> URL parameters should be:
 *   utm_source={{site_source_name}}&utm_medium=paid&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&utm_term={{adset.name}}&campaign_id={{campaign.id}}&adset_id={{adset.id}}&ad_id={{ad.id}}&placement={{placement}}
 */
class Attribution
{
    public const FIRST_COOKIE = 'ys_attr_first';
    public const LAST_COOKIE = 'ys_attr_last';
    public const COOKIE_MINUTES = 60 * 24 * 30;

    /** order column => accepted query parameter names (first match wins) */
    private const PARAMS = [
        'utm_source' => ['utm_source'],
        'utm_medium' => ['utm_medium'],
        'utm_campaign' => ['utm_campaign'],
        'utm_content' => ['utm_content'],
        'utm_term' => ['utm_term'],
        'campaign_id' => ['campaign_id', 'fb_campaign_id', 'utm_id'],
        'campaign_name' => ['campaign_name', 'fb_campaign_name'],
        'adset_id' => ['adset_id', 'ad_set_id', 'fb_adset_id'],
        'adset_name' => ['adset_name', 'ad_set_name'],
        'ad_id' => ['ad_id', 'fb_ad_id', 'h_ad_id'],
        'ad_name' => ['ad_name'],
        'placement' => ['placement'],
        'site_source' => ['site_source', 'site_source_name'],
        'fbclid' => ['fbclid'],
        'gclid' => ['gclid'],
    ];

    private const MAX_LENGTH = ['fbclid' => 500, 'campaign_id' => 64, 'adset_id' => 64, 'ad_id' => 64, 'placement' => 100, 'site_source' => 50];

    /** Landing data from the current request, or null when there is nothing worth recording. */
    public static function capture(Request $request): ?array
    {
        $params = [];
        foreach (self::PARAMS as $column => $names) {
            foreach ($names as $name) {
                $value = $request->query($name);
                // skip arrays and unreplaced Meta macros like {{campaign.name}}
                if (!is_string($value) || ($value = trim($value)) === '' || str_contains($value, '{{')) {
                    continue;
                }
                $params[$column] = mb_substr($value, 0, self::MAX_LENGTH[$column] ?? 255);
                break;
            }
        }

        $referrer = (string) $request->headers->get('referer', '');
        $referrerHost = parse_url($referrer, PHP_URL_HOST);
        $externalReferrer = $referrerHost && $referrerHost !== $request->getHost();

        if (!$params && !$externalReferrer) {
            return null; // internal navigation / direct visit: keep what we already have
        }

        return self::fitCookie([
            'params' => $params,
            // params are stored separately, so the URL without its query string is enough
            'landing_page' => mb_substr($request->url(), 0, 300),
            // host + path only: l.facebook.com/l.php?u=...&h=... style query strings are huge
            'referrer' => $externalReferrer
                ? mb_substr(strtok($referrer, '?#'), 0, 200)
                : null,
            'at' => now()->getTimestampMs(),
        ]);
    }

    /**
     * Browsers silently drop cookies over ~4 KB, and Laravel's encryption roughly doubles the size.
     * Keep the JSON under budget by halving the longest parameter (long Bangla names are 3 bytes/char).
     */
    private static function fitCookie(array $touch): array
    {
        $budget = 1800;
        while (strlen(json_encode($touch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > $budget) {
            $longest = collect($touch['params'])->sortByDesc(fn($v) => strlen($v))->keys()->first();
            if ($longest === null || mb_strlen($touch['params'][$longest]) <= 20) {
                $touch['landing_page'] = mb_substr((string) $touch['landing_page'], 0, 100);
                $touch['referrer'] = $touch['referrer'] ? mb_substr($touch['referrer'], 0, 100) : null;
                break;
            }
            $touch['params'][$longest] = mb_substr($touch['params'][$longest], 0, (int) (mb_strlen($touch['params'][$longest]) / 2));
        }

        return $touch;
    }

    /** False until the attribution migration has run, so deploying code first can't break checkout. */
    public static function columnsReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasColumn('orders', 'attribution');
    }

    /** Order column values for the visitor behind this request (all keys present, null when unknown). */
    public static function forOrder(Request $request): array
    {
        $first = self::readCookie($request, self::FIRST_COOKIE);
        $last = self::readCookie($request, self::LAST_COOKIE);
        $touch = $last ?? $first;

        $columns = array_fill_keys(array_keys(self::PARAMS), null);
        foreach ($touch['params'] ?? [] as $column => $value) {
            if (array_key_exists($column, $columns) && is_string($value)) {
                $columns[$column] = $value;
            }
        }

        // Meta browser ids: _fbp/_fbc are set by the pixel (excluded from cookie encryption)
        $fbp = $request->cookie('_fbp');
        $fbc = $request->cookie('_fbc');
        if (!$fbc && $columns['fbclid']) {
            // same format the pixel uses: fb.1.<click time ms>.<fbclid>
            $fbc = 'fb.1.' . ($touch['at'] ?? now()->getTimestampMs()) . '.' . $columns['fbclid'];
        }

        return $columns + [
            'fbp' => is_string($fbp) ? mb_substr($fbp, 0, 255) : null,
            'fbc' => is_string($fbc) ? mb_substr($fbc, 0, 500) : null,
            'landing_page' => $touch['landing_page'] ?? null,
            'referrer' => $touch['referrer'] ?? null,
            'attribution' => ($first || $last) ? ['first_touch' => $first, 'last_touch' => $last] : null,
        ];
    }

    private static function readCookie(Request $request, string $name): ?array
    {
        $data = json_decode((string) $request->cookie($name), true);

        return is_array($data) ? $data : null;
    }
}
