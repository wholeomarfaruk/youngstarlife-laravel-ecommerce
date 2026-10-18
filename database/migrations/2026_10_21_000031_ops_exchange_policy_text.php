<?php

use App\Models\Ops\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Exchange policy changed: exchange is no longer free (delivery charge applies) and must be asked for within 24 hours.
 * Rewrites only the two old FAQ lines (Settings → Website texts) — any other line someone wrote is left alone.
 */
return new class extends Migration
{
    private const OLD = [
        'সমস্ত এক্সচেঞ্জে উপভোগ করুন সম্পূর্ণ ফ্রি ডেলিভারি — কোন অতিরিক্ত চার্জ নেই, কোন ঝামেলা নেই।' => 'এক্সচেঞ্জ ফ্রি নয় — এক্সচেঞ্জের ক্ষেত্রে ডেলিভারি চার্জ প্রযোজ্য।',
        'আমাদের আছে ডেলিভারির পর ৩ দিন পর্যন্ত এক্সচেঞ্জ সুবিধা।' => 'এক্সচেঞ্জ করতে চাইলে প্রোডাক্ট হাতে পাওয়ার সর্বোচ্চ ২৪ ঘণ্টার মধ্যে আমাদের জানাতে হবে।',
    ];

    public function up(): void { $this->swap(self::OLD); }

    public function down(): void { $this->swap(array_flip(self::OLD)); }

    private function swap(array $map): void
    {
        $content = Setting::get('content');
        if (! is_array($content) || ! is_array($content['faq'] ?? null)) return;   // never saved → the new defaults apply
        $content['faq'] = array_map(fn ($line) => $map[trim((string) $line)] ?? $line, $content['faq']);
        Setting::put('content', $content);
    }
};
