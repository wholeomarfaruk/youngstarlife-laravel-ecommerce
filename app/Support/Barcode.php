<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Product barcodes: EAN-13 = company prefix (Settings → Barcode & labels) + item number + check digit.
 * Every variant gets one automatically (App\Models\Ops\ProductVariant); a supplier's own barcode typed in is kept as-is.
 */
class Barcode
{
    /** EAN-13 check digit for the first 12 digits */
    public static function checkDigit(string $twelve): int
    {
        $sum = 0;
        foreach (str_split($twelve) as $i => $d) $sum += (int) $d * ($i % 2 ? 3 : 1);
        return (10 - $sum % 10) % 10;
    }

    public static function prefix(): string
    {
        $p = preg_replace('/\D/', '', (string) ops_setting('barcode.prefix', '890123'));
        return substr($p !== '' ? $p : '200', 0, 9);   // leave at least 3 digits for the item number
    }

    /** a free EAN-13 for this variant id (the id makes it stable; on a clash the next free number is used) */
    public static function forVariant(int $variantId): string
    {
        $prefix = self::prefix(); $room = 12 - strlen($prefix); $max = 10 ** $room;
        for ($n = $variantId % $max, $tries = 0; $tries < 1000; $n = ($n + 1) % $max, $tries++) {
            $body = $prefix.str_pad((string) $n, $room, '0', STR_PAD_LEFT);
            $code = $body.self::checkDigit($body);
            if (! DB::table('ops_product_variants')->where('barcode', $code)->where('id', '!=', $variantId)->exists()) return $code;
        }
        throw new \RuntimeException('No free barcode number left for prefix '.$prefix.' — use a shorter prefix in Settings → Barcode & labels.');
    }
}
