<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;

class SpamFilter
{
    public function isSpam(string $fromEmail, string $subject, string $body, array $headers = []): bool|string
    {
        // 1. Blocklist check
        if ($this->isBlocklisted($fromEmail)) {
            return 'blocklisted';
        }

        // 2. Allowlist check — if allowlist is configured, only allow those domains
        if ($this->hasAllowlist() && !$this->isAllowlisted($fromEmail)) {
            return 'not_allowlisted';
        }

        // 3. SpamAssassin / X-Spam header check
        if ($this->isMarkedAsSpam($headers)) {
            return 'spam_header';
        }

        // 4. Rate limiting — max tickets per sender per hour
        if ($this->isRateLimited($fromEmail)) {
            return 'rate_limited';
        }

        return false;
    }

    protected function isBlocklisted(string $email): bool
    {
        $blocklist = config('support.spam.blocklist', []);
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        $email = strtolower($email);

        foreach ($blocklist as $entry) {
            $entry = strtolower($entry);
            if ($entry === $email || $entry === $domain) {
                return true;
            }
        }

        return false;
    }

    protected function hasAllowlist(): bool
    {
        $allowlist = config('support.spam.allowlist', []);

        return !empty($allowlist);
    }

    protected function isAllowlisted(string $email): bool
    {
        $allowlist = config('support.spam.allowlist', []);
        $domain = strtolower(substr($email, strrpos($email, '@') + 1));
        $email = strtolower($email);

        foreach ($allowlist as $entry) {
            $entry = strtolower($entry);
            if ($entry === $email || $entry === $domain) {
                return true;
            }
        }

        return false;
    }

    protected function isMarkedAsSpam(array $headers): bool
    {
        $spamHeaders = [
            'x-spam-status',
            'x-spam-flag',
        ];

        foreach ($spamHeaders as $header) {
            $value = $headers[$header] ?? '';
            if (is_string($value) && preg_match('/^(yes|true|spam)/i', trim($value))) {
                return true;
            }
        }

        // Check X-Spam-Score threshold
        $score = $headers['x-spam-score'] ?? null;
        $threshold = config('support.spam.score_threshold', 5.0);
        if ($score !== null && (float) $score >= $threshold) {
            return true;
        }

        return false;
    }

    protected function isRateLimited(string $email): bool
    {
        $maxPerHour = config('support.spam.max_tickets_per_hour', 10);
        $cacheKey = 'spam_rate:' . strtolower($email);

        $count = Cache::get($cacheKey, 0);

        if ($count >= $maxPerHour) {
            return true;
        }

        Cache::put($cacheKey, $count + 1, now()->addHour());

        return false;
    }
}
