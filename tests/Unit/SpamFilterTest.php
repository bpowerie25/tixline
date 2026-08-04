<?php

namespace Tests\Unit;

use App\Services\SpamFilter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SpamFilterTest extends TestCase
{
    protected SpamFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new SpamFilter;
        Cache::flush();
    }

    public function test_clean_email_passes(): void
    {
        $result = $this->filter->isSpam('user@example.com', 'Hello', 'Body');
        $this->assertFalse($result);
    }

    public function test_blocklisted_email_is_rejected(): void
    {
        config(['support.spam.blocklist' => ['spammer@evil.com']]);
        $this->assertEquals('blocklisted', $this->filter->isSpam('spammer@evil.com', 'Buy', 'Spam'));
    }

    public function test_blocklisted_domain_is_rejected(): void
    {
        config(['support.spam.blocklist' => ['evil.com']]);
        $this->assertEquals('blocklisted', $this->filter->isSpam('anyone@evil.com', 'S', 'B'));
    }

    public function test_allowlist_rejects_unlisted_domain(): void
    {
        config(['support.spam.allowlist' => ['trusted.com']]);
        $this->assertEquals('not_allowlisted', $this->filter->isSpam('user@untrusted.com', 'S', 'B'));
    }

    public function test_allowlist_accepts_listed_domain(): void
    {
        config(['support.spam.allowlist' => ['trusted.com']]);
        $this->assertFalse($this->filter->isSpam('user@trusted.com', 'S', 'B'));
    }

    public function test_spam_header_yes_is_rejected(): void
    {
        $this->assertEquals('spam_header', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'x-spam-status' => 'Yes, score=8.5',
        ]));
    }

    public function test_spam_flag_true_is_rejected(): void
    {
        $this->assertEquals('spam_header', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'x-spam-flag' => 'TRUE',
        ]));
    }

    public function test_spam_score_above_threshold_is_rejected(): void
    {
        config(['support.spam.score_threshold' => 5.0]);
        $this->assertEquals('spam_header', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'x-spam-score' => '7.5',
        ]));
    }

    public function test_spam_score_below_threshold_passes(): void
    {
        config(['support.spam.score_threshold' => 5.0]);
        $this->assertFalse($this->filter->isSpam('u@e.com', 'S', 'B', [
            'x-spam-score' => '2.1',
        ]));
    }

    public function test_rate_limiting_kicks_in(): void
    {
        config(['support.spam.max_tickets_per_hour' => 2]);
        $this->assertFalse($this->filter->isSpam('u@e.com', 'S1', 'B'));
        $this->assertFalse($this->filter->isSpam('u@e.com', 'S2', 'B'));
        $this->assertEquals('rate_limited', $this->filter->isSpam('u@e.com', 'S3', 'B'));
    }

    public function test_rate_limit_is_per_sender(): void
    {
        config(['support.spam.max_tickets_per_hour' => 1]);
        $this->assertFalse($this->filter->isSpam('a@e.com', 'S', 'B'));
        $this->assertFalse($this->filter->isSpam('b@e.com', 'S', 'B'));
        $this->assertEquals('rate_limited', $this->filter->isSpam('a@e.com', 'S', 'B'));
    }

    public function test_rate_limit_is_tenant_scoped(): void
    {
        config(['support.spam.max_tickets_per_hour' => 1]);
        $this->assertFalse($this->filter->isSpam('u@e.com', 'S', 'B', [], 1));
        $this->assertFalse($this->filter->isSpam('u@e.com', 'S', 'B', [], 2));
        $this->assertEquals('rate_limited', $this->filter->isSpam('u@e.com', 'S', 'B', [], 1));
    }

    public function test_blocklist_is_case_insensitive(): void
    {
        config(['support.spam.blocklist' => ['Evil.COM']]);
        $this->assertEquals('blocklisted', $this->filter->isSpam('user@evil.com', 'S', 'B'));
    }

    // Auto-submitted detection
    public function test_auto_submitted_auto_replied(): void
    {
        $this->assertEquals('auto_submitted', $this->filter->isSpam('u@e.com', 'OOO', 'B', [
            'auto-submitted' => 'auto-replied',
        ]));
    }

    public function test_auto_submitted_auto_generated(): void
    {
        $this->assertEquals('auto_submitted', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'auto-submitted' => 'auto-generated',
        ]));
    }

    public function test_auto_submitted_no_passes(): void
    {
        $this->assertFalse($this->filter->isSpam('u@e.com', 'S', 'B', [
            'auto-submitted' => 'no',
        ]));
    }

    public function test_precedence_bulk_rejected(): void
    {
        $this->assertEquals('auto_submitted', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'precedence' => 'bulk',
        ]));
    }

    public function test_precedence_junk_rejected(): void
    {
        $this->assertEquals('auto_submitted', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'precedence' => 'junk',
        ]));
    }

    public function test_microsoft_auto_response_suppress(): void
    {
        $this->assertEquals('auto_submitted', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'x-auto-response-suppress' => 'All',
        ]));
    }

    // Bounce/NDR detection
    public function test_mailer_daemon_is_bounce(): void
    {
        $this->assertEquals('bounce', $this->filter->isSpam('MAILER-DAEMON@server.com', 'S', 'B', [
            'return-path' => '<>',
        ]));
    }

    public function test_multipart_report_is_bounce(): void
    {
        $this->assertEquals('bounce', $this->filter->isSpam('u@e.com', 'S', 'B', [
            'content-type' => 'multipart/report; report-type=delivery-status',
        ]));
    }

    public function test_noreply_sender_is_bounce(): void
    {
        $this->assertEquals('bounce', $this->filter->isSpam('noreply@example.com', 'S', 'B'));
    }

    public function test_postmaster_is_bounce(): void
    {
        $this->assertEquals('bounce', $this->filter->isSpam('postmaster@example.com', 'S', 'B'));
    }

    // Log-only mode
    public function test_log_only_mode_passes_but_logs(): void
    {
        config(['support.spam.log_only' => true, 'support.spam.blocklist' => ['evil.com']]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'log-only') && $context['reason'] === 'blocklisted';
            });

        $result = $this->filter->isSpam('user@evil.com', 'Subject', 'Body');
        $this->assertFalse($result);
    }

    public function test_log_only_mode_does_not_reject(): void
    {
        config(['support.spam.log_only' => true, 'support.spam.max_tickets_per_hour' => 0]);

        $result = $this->filter->isSpam('user@example.com', 'S', 'B');
        $this->assertFalse($result);
    }
}
