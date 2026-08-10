<?php

namespace App\Services;

use App\Models\SpamFilterEntry;
use App\Services\SpamLearner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SpamFilter
{
    public function isSpam(string $fromEmail, string $subject, string $body, array $headers = [], ?int $tenantId = null): bool|string
    {
        $logOnly = config('support.spam.log_only', false);

        // Decode MIME-encoded subjects before checking
        $decodedSubject = mb_decode_mimeheader($subject);

        $checks = [
            fn () => $this->checkAutoSubmitted($headers),
            fn () => $this->checkAutoReplySubject($decodedSubject),
            fn () => $this->checkBounceOrNdr($fromEmail, $headers),
            fn () => $this->isBlocklisted($fromEmail) ? 'blocklisted' : false,
            fn () => ($this->hasAllowlist() && ! $this->isAllowlisted($fromEmail)) ? 'not_allowlisted' : false,
            fn () => $this->isMarkedAsSpam($headers) ? 'spam_header' : false,
            fn () => $this->checkLearnedSpam($fromEmail, $decodedSubject, $body),
            fn () => $this->isRateLimited($fromEmail, $tenantId) ? 'rate_limited' : false,
        ];

        foreach ($checks as $check) {
            $result = $check();
            if ($result !== false) {
                if ($logOnly) {
                    Log::info('SpamFilter (log-only): would reject', [
                        'reason' => $result,
                        'from' => $fromEmail,
                        'subject' => $subject,
                    ]);

                    return false;
                }

                return $result;
            }
        }

        return false;
    }

    protected function checkAutoSubmitted(array $headers): bool|string
    {
        $autoSubmitted = strtolower($headers['auto-submitted'] ?? '');

        // RFC 3834: "auto-replied", "auto-generated", "auto-notified" — never "no"
        if ($autoSubmitted && $autoSubmitted !== 'no') {
            return 'auto_submitted';
        }

        // Precedence: bulk or junk (mailing lists, auto-responders)
        $precedence = strtolower($headers['precedence'] ?? '');
        if (in_array($precedence, ['bulk', 'junk', 'list'])) {
            return 'auto_submitted';
        }

        // X-Auto-Response-Suppress (Microsoft)
        if (! empty($headers['x-auto-response-suppress'])) {
            return 'auto_submitted';
        }

        return false;
    }

    protected function checkAutoReplySubject(string $subject): bool|string
    {
        $lower = strtolower($subject);
        $patterns = [
            'out of office',
            'automatic reply',
            'auto-reply',
            'autoreply',
            'abwesenheitsnotiz',    // German OOO
            'abwesenheit bis',      // German OOO variant ("absence until")
            'automatische antwort', // German auto-reply
            'absence du bureau',    // French OOO
            'respuesta automática', // Spanish auto-reply
            'fuera de la oficina',  // Spanish OOO
            'risposta automatica',  // Italian auto-reply
            'fuori dall\'ufficio',  // Italian OOO (escaped)
            'fuori dall\u2019ufficio', // Italian OOO (smart quote)
        ];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return 'auto_reply_subject';
            }
        }

        return false;
    }

    protected function checkBounceOrNdr(string $fromEmail, array $headers): bool|string
    {
        // Null sender (MAILER-DAEMON, empty return-path) = bounce
        $returnPath = $headers['return-path'] ?? '';
        if ($returnPath === '<>' || $returnPath === '') {
            // Only if other headers also indicate a bounce
            $from = strtolower($fromEmail);
            if (str_contains($from, 'mailer-daemon') || str_contains($from, 'postmaster')) {
                return 'bounce';
            }
        }

        // Content-Type: multipart/report = DSN/NDR
        $contentType = strtolower($headers['content-type'] ?? '');
        if (str_contains($contentType, 'multipart/report')) {
            return 'bounce';
        }

        // Common bounce sender patterns
        $from = strtolower($fromEmail);
        $bouncePatterns = ['mailer-daemon@', 'postmaster@', 'noreply@', 'no-reply@'];
        foreach ($bouncePatterns as $pattern) {
            if (str_starts_with($from, $pattern)) {
                return 'bounce';
            }
        }

        return false;
    }

    protected function isBlocklisted(string $email): bool
    {
        $blocklist = array_merge(
            config('support.spam.blocklist', []),
            SpamFilterEntry::blocklist()
        );
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
        return ! empty(config('support.spam.allowlist', [])) || SpamFilterEntry::where('type', 'allowlist')->exists();
    }

    protected function isAllowlisted(string $email): bool
    {
        $allowlist = array_merge(
            config('support.spam.allowlist', []),
            SpamFilterEntry::allowlist()
        );
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
        $spamHeaders = ['x-spam-status', 'x-spam-flag'];

        foreach ($spamHeaders as $header) {
            $value = $headers[$header] ?? '';
            if (is_string($value) && preg_match('/^(yes|true|spam)/i', trim($value))) {
                return true;
            }
        }

        $score = $headers['x-spam-score'] ?? null;
        $threshold = config('support.spam.score_threshold', 5.0);
        if ($score !== null && (float) $score >= $threshold) {
            return true;
        }

        return false;
    }

    protected function isRateLimited(string $email, ?int $tenantId = null): bool
    {
        $maxPerHour = config('support.spam.max_tickets_per_hour', 10);
        // Tenant-scoped rate limit key
        $scope = $tenantId ? "t{$tenantId}" : 'global';
        $cacheKey = "spam_rate:{$scope}:".strtolower($email);

        $count = Cache::get($cacheKey, 0);

        if ($count >= $maxPerHour) {
            return true;
        }

        Cache::put($cacheKey, $count + 1, now()->addHour());

        return false;
    }

    protected function checkLearnedSpam(string $fromEmail, string $subject, string $body): bool|string
    {
        $learner = app(SpamLearner::class);
        $score = $learner->score($fromEmail, $subject, $body);
        $threshold = config('support.spam.learned_threshold', 8.0);

        if ($score >= $threshold) {
            Log::info('SpamFilter: learned spam detected', [
                'from' => $fromEmail,
                'subject' => $subject,
                'score' => $score,
                'threshold' => $threshold,
            ]);

            return 'learned_spam (score: '.$score.')';
        }

        return false;
    }
}
