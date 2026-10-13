<?php
// composer.json → "autoload": { "files": ["app/helpers.php"] }
if (! function_exists('tk')) {
    function tk(int|float|null $n): string { return config('admin.currency').number_format((float) $n); }
}
if (! function_exists('status_badge')) {
    /** status_badge('order_status', 'packed') → ['Packed', 'badge-info'] */
    function status_badge(string $map, ?string $key): array {
        [$label, $tone] = config("admin.$map.$key", [ucfirst((string) $key), 'mute']);
        return [$label, 'badge-'.$tone];
    }
}
