<?php

namespace App\Services\Ops;

use App\Models\User;
use Illuminate\Support\Str;

/** Authenticator-app codes (RFC 6238 TOTP: SHA-1, 6 digits, 30 s) + one-time recovery codes. No extra package needed. */
class TwoFactor
{
    protected const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        $s = ''; for ($i = 0; $i < 32; $i++) $s .= self::ALPHABET[random_int(0, 31)];
        return $s;
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        $issuer = config('app.name').' Ops';
        return 'otpauth://totp/'.rawurlencode($issuer.':'.($user->email ?: $user->phone ?: $user->name)).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function qrSvg(string $url): string
    {
        return (new \Milon\Barcode\DNS2D)->getBarcodeSVG($url, 'QRCODE', 4, 4);
    }

    /** accepts the previous / current / next 30 s window; a code that already signed in can't be replayed */
    public function verify(string $secret, string $code, ?int $userId = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;
        $now = intdiv(time(), 30);
        foreach ([0, -1, 1] as $drift) {
            if (! hash_equals($this->at($secret, $now + $drift), $code)) continue;
            return $userId === null || cache()->add("2fa-used:$userId:".($now + $drift), true, 120);
        }
        return false;
    }

    /** @return string[] 8 codes like a1b2c-3d4e5 */
    public function recoveryCodes(): array
    {
        return array_map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)), range(1, 8));
    }

    /** a recovery code works once; true when it matched (and was used up) */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->twoFactorRecoveryCodes();
        $i = array_search(Str::lower(trim($code)), $codes, true);
        if ($i === false) return false;
        unset($codes[$i]);
        $user->forceFill(['two_factor_recovery_codes' => encrypt(json_encode(array_values($codes)))])->save();
        return true;
    }

    protected function at(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $this->base32Decode($secret), true);
        $o = ord($hash[19]) & 0xf;
        $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    protected function base32Decode(string $s): string
    {
        $bits = ''; foreach (str_split(strtoupper($s)) as $c) $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        return implode('', array_map(fn ($b) => chr(bindec($b)), array_filter(str_split($bits, 8), fn ($b) => strlen($b) === 8)));
    }
}
