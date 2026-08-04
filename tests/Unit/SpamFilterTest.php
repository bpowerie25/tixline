<?php

namespace Tests\Unit;

use App\Services\SpamFilter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SpamFilterTest extends TestCase
{
    protected SpamFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new SpamFilter();
    }

    public function test_clean_email_passes(): void
    {
        $result = $this->filter->isSpam('user@example.com', 'Hello', 'Body');
        $this->assertFalse($result);
    }

    public function test_blocklisted_email_is_rejected(): void
    {
        config(['support.spam.blocklist' => ['spammer@evil.com']]);
        $result = $this->filter->isSpam('spammer@evil.com', 'Buy now', 'Spam body');
        $this->assertEquals('blocklisted', $result);
    }

    public function test_blocklisted_domain_is_rejected(): void
    {
        config(['support.spam.blocklist' => ['evil.com']]);
        $result = $this->filter->isSpam('anyone@evil.com', 'Subject', 'Body');
        $this->assertEquals('blocklisted', $result);
    }

    public function test_allowlist_rejects_unlisted_domain(): void
    {
        config(['support.spam.allowlist' => ['trusted.com']]);
        $result = $this->filter->isSpam('user@untrusted.com', 'Subject', 'Body');
        $this->assertEquals('not_allowlisted', $result);
    }

    public function test_allowlist_accepts_listed_domain(): void
    {
        config(['support.spam.allowlist' => ['trusted.com']]);
        $result = $this->filter->isSpam('user@trusted.com', 'Subject', 'Body');
        $this->assertFalse($result);
    }

    public function test_allowlist_accepts_specific_email(): void
    {
        config(['support.spam.allowlist' => ['vip@gmail.com']]);
        $result = $this->filter->isSpam('vip@gmail.com', 'Subject', 'Body');
        $this->assertFalse($result);
    }

    public function test_spam_header_yes_is_rejected(): void
    {
        $result = $this->filter->isSpam('user@example.com', 'Subject', 'Body', [
            'x-spam-status' => 'Yes, score=8.5',
        ]);
        $this->assertEquals('spam_header', $result);
    }

    public function test_spam_flag_true_is_rejected(): void
    {
        $result = $this->filter->isSpam('user@example.com', 'Subject', 'Body', [
            'x-spam-flag' => 'TRUE',
        ]);
        $this->assertEquals('spam_header', $result);
    }

    public function test_spam_score_above_threshold_is_rejected(): void
    {
        config(['support.spam.score_threshold' => 5.0]);
        $result = $this->filter->isSpam('user@example.com', 'Subject', 'Body', [
            'x-spam-score' => '7.5',
        ]);
        $this->assertEquals('spam_header', $result);
    }

    public function test_spam_score_below_threshold_passes(): void
    {
        config(['support.spam.score_threshold' => 5.0]);
        $result = $this->filter->isSpam('user@example.com', 'Subject', 'Body', [
            'x-spam-score' => '2.1',
        ]);
        $this->assertFalse($result);
    }

    public function test_rate_limiting_kicks_in(): void
    {
        Cache::flush();
        config(['support.spam.max_tickets_per_hour' => 2]);

        $this->assertFalse($this->filter->isSpam('user@example.com', 'S1', 'B'));
        $this->assertFalse($this->filter->isSpam('user@example.com', 'S2', 'B'));
        $this->assertEquals('rate_limited', $this->filter->isSpam('user@example.com', 'S3', 'B'));
    }

    public function test_rate_limit_is_per_sender(): void
    {
        Cache::flush();
        config(['support.spam.max_tickets_per_hour' => 1]);

        $this->assertFalse($this->filter->isSpam('a@example.com', 'S', 'B'));
        $this->assertFalse($this->filter->isSpam('b@example.com', 'S', 'B'));
        $this->assertEquals('rate_limited', $this->filter->isSpam('a@example.com', 'S', 'B'));
    }

    public function test_blocklist_is_case_insensitive(): void
    {
        config(['support.spam.blocklist' => ['Evil.COM']]);
        $result = $this->filter->isSpam('user@evil.com', 'Subject', 'Body');
        $this->assertEquals('blocklisted', $result);
    }
}
