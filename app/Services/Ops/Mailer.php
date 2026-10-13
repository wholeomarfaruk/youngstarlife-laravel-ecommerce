<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Mail;

/** Sends Ops mail through Settings → Integrations → Email SMTP (falls back to the app's mail config when SMTP isn't filled in). */
class Mailer
{
    public function mailer()
    {
        $s = ops_setting('smtp');
        if (empty($s['host']) || empty($s['username'])) return Mail::mailer();
        config(['mail.mailers.ops_smtp' => ['transport' => 'smtp', 'host' => $s['host'], 'port' => (int) $s['port'], 'encryption' => ($s['encryption'] ?? 'tls') === 'none' ? null : $s['encryption'],
            'username' => $s['username'], 'password' => $s['password'] ?? '', 'timeout' => 15]]);
        return Mail::mailer('ops_smtp');
    }

    /** @param array<string,string> $attachments filename => content */
    public function send(string $to, string $subject, string $body, array $attachments = []): void
    {
        $s = ops_setting('smtp');
        $this->mailer()->raw($body, function ($m) use ($to, $subject, $attachments, $s) {
            $m->to($to)->subject($subject);
            if (! empty($s['from_email'])) $m->from($s['from_email'], $s['from_name'] ?? 'YoungStar Ops');
            if (! empty($s['reply_to'])) $m->replyTo($s['reply_to']);
            foreach ($attachments as $name => $content) $m->attachData($content, $name, ['mime' => 'text/csv']);
        });
    }
}
