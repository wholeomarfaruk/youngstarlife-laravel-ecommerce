<?php

namespace App\Support;

class Phone
{
    private const LOCAL_DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9', // Bangla
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', // Arabic-Indic
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', // Persian
    ];

    /**
     * Normalize any way a customer writes a Bangladeshi mobile number to 01XXXXXXXXX.
     *
     *   +8801684285963, 8801684285963, 801684285963, 008801684285963, 88001684285963,
     *   1684285963, 01684-285963, 01684 285 963, (+880) 1684-285963, ০১৬৮৪২৮৫৯৬৩  =>  01684285963
     *
     * Returns null when the input is not a valid BD mobile number.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', strtr($input, self::LOCAL_DIGITS));
        if (strlen($digits) < 10) {
            return null;
        }

        // subscriber part: 1 + operator (3-9) + 8 digits
        $number = substr($digits, -10);
        // whatever was typed in front of it: nothing, 0, 88, 880, +88 0, 00 880, or a dropped 8 (80)
        $prefix = substr($digits, 0, -10);

        if (!preg_match('/^1[3-9]\d{8}$/', $number) || !preg_match('/^(00)?8{0,2}0{0,2}$/', $prefix)) {
            return null;
        }

        return '0' . $number;
    }

    /**
     * International (E.164) form for ad platforms: 01684285963 => +8801684285963.
     * Meta's pixel strips leading zeros and only trusts a country code when the number starts with "+"
     * (otherwise it guesses one from the browser language, which has no BD entry); Google requires E.164.
     */
    public static function toE164(?string $input): ?string
    {
        $local = self::normalize($input);

        return $local ? '+88' . $local : null;
    }

    /** Normalized number, or the input unchanged when it can't be normalized (for admin-entered data). */
    public static function normalizeOrKeep(?string $input): ?string
    {
        return self::normalize($input) ?? $input;
    }
}
