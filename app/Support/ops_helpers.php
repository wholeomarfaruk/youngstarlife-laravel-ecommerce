<?php
// Ops admin panel helpers (autoloaded via composer.json "files").

if (! function_exists('ops_num')) {
    /** Indian digit grouping: 186300 → 1,86,300 */
    function ops_num(int|float|null $n): string
    {
        $n = (int) round((float) $n); $neg = $n < 0; $s = (string) abs($n);
        if (strlen($s) > 3) $s = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3)).','.substr($s, -3);
        return ($neg ? '−' : '').$s;
    }
}
if (! function_exists('tk')) {
    /** ৳1,86,300 (integers only, Indian grouping) */
    function tk(int|float|null $n): string { $n = (int) round((float) $n); return ($n < 0 ? '−' : '').config('ops.currency').ops_num(abs($n)); }
}
if (! function_exists('status_badge')) {
    /** status_badge('order_status', 'packed') → ['Packed', 'badge-info'] */
    function status_badge(string $map, ?string $key): array {
        [$label, $tone] = config("ops.$map.$key", [ucfirst(str_replace('_', ' ', (string) $key)), 'mute']);
        return [$label, 'badge-'.$tone];
    }
}
if (! function_exists('ops_setting')) {
    /** ops_setting('orders.prefix') — dotted read of the Ops settings store with design defaults. */
    function ops_setting(string $key, $default = null)
    {
        return site($key, $default);
    }
}
if (! function_exists('site')) {
    /** site() → App\Support\SiteSettings · site('seo.title') → dotted read (defaults + stored, cached) */
    function site(?string $key = null, $default = null)
    {
        $s = app(\App\Support\SiteSettings::class);
        return $key === null ? $s : $s->get($key, $default);
    }
}
if (! function_exists('ops_log')) {
    /** Activity log entry (Settings → Team → Activity log, Dashboard → Recent activity). */
    function ops_log(string $message, $subject = null): void
    {
        \Illuminate\Support\Facades\DB::table('ops_activity_log')->insert(['user_id' => auth()->id(), 'message' => mb_substr($message, 0, 250),
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'created_at' => now()]);
    }
}
if (! function_exists('ops_notify')) {
    /** In-app notification to every Ops user who has this event on (Settings → Web push matrix). */
    function ops_notify(string $event, string $message, ?string $url = null): void
    {
        try { app(\App\Services\Ops\Notifier::class)->send($event, $message, $url); } catch (\Throwable $e) { report($e); }
    }
}
if (! function_exists('ops_date')) {
    /** 17 Mar · with time: 17 Mar 11:05 */
    function ops_date($d, bool $time = false): string { return $d ? \Illuminate\Support\Carbon::parse($d)->format($time ? 'd M H:i' : 'd M') : '—'; }
}
