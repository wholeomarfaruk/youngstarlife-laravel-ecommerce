<?php

namespace App\OrderIntake;

use App\Models\Ops\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Settings → AI Order (settings group "ai_order"), with the defaults the
 * feature runs on before anyone opens that tab. The OpenRouter key is
 * stored encrypted and never leaves the server; OPENROUTER_API_KEY in .env
 * is used when none is saved.
 */
class IntakeSettings
{
    /** one value of the Ops settings group "ai_order" */
    protected static function get(string $key, $default = null)
    {
        $v = (Setting::get(self::GROUP, []) ?: [])[$key] ?? null;
        return $v === null || $v === '' ? $default : $v;
    }

    public static function set(string $key, $value): void
    {
        Setting::put(self::GROUP, array_merge(Setting::get(self::GROUP, []) ?: [], [$key => $value]));
    }

    public const GROUP = 'ai_order';

    public const DEFAULT_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain'];

    public function enabled(): bool
    {
        return (bool) self::get('enabled', '0');
    }

    /** AI fallback can actually run: switched on and a key available. */
    public function aiAvailable(): bool
    {
        return $this->enabled() && $this->apiKey() !== '';
    }

    public function apiKey(): string
    {
        $stored = (string) self::get('api_key', '');

        if ($stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (DecryptException) {
                return '';
            }
        }

        return (string) config('services.openrouter.api_key', '');
    }

    public function hasStoredKey(): bool
    {
        return (string) self::get('api_key', '') !== '';
    }

    public static function storeApiKey(?string $key): void
    {
        self::set('api_key', $key ? Crypt::encryptString($key) : '');
    }

    /** Last four characters, for "saved: ••••abcd". */
    public function maskedKey(): string
    {
        $key = $this->apiKey();

        return $key === '' ? '' : '••••' . substr($key, -4);
    }

    public function keySource(): string
    {
        return $this->hasStoredKey() ? 'settings' : ((string) config('services.openrouter.api_key', '') !== '' ? 'env' : 'none');
    }

    public function model(): string
    {
        return (string) (self::get('model', '') ?: config('services.openrouter.model', 'openrouter/free'));
    }

    public function fallbackModel(): string
    {
        return (string) self::get('fallback_model', '');
    }

    public function temperature(): float
    {
        return (float) self::get('temperature', '0');
    }

    public function timeout(): int
    {
        return max(5, min(120, (int) self::get('timeout', '25')));
    }

    public function maxOutputTokens(): int
    {
        return max(500, min(16000, (int) self::get('max_tokens', '3000')));
    }

    /** AI calls allowed per day across the store (0 = no limit). */
    public function dailyLimit(): int
    {
        return max(0, (int) self::get('daily_limit', '200'));
    }

    /** AI calls one admin may make per minute. */
    public function perMinute(): int
    {
        return max(1, (int) self::get('per_minute', '6'));
    }

    public function maxFiles(): int
    {
        return max(1, min(10, (int) self::get('max_files', '4')));
    }

    public function maxFileKb(): int
    {
        return max(100, min(20480, (int) self::get('max_file_kb', '4096')));
    }

    /** @return list<string> */
    public function allowedMimes(): array
    {
        $mimes = self::get('allowed_mimes', null);
        $list = is_array($mimes) ? $mimes : array_filter(array_map('trim', explode(',', (string) $mimes)));

        return $list ? array_values($list) : self::DEFAULT_MIMES;
    }

    /** How many catalogue products the AI is shown at most. */
    public function maxCandidates(): int
    {
        return max(10, min(200, (int) self::get('max_candidates', '40')));
    }

    /** Store-specific instructions appended to the system prompt. */
    public function extraPrompt(): string
    {
        return mb_substr((string) self::get('extra_prompt', ''), 0, 2000);
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
    }
}
