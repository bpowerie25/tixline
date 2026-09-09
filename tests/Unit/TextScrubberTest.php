<?php

namespace Tests\Unit;

use App\Support\TextScrubber;
use PHPUnit\Framework\TestCase;

class TextScrubberTest extends TestCase
{
    protected TextScrubber $scrubber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scrubber = new TextScrubber;
        $this->scrubber->setNames(['John Smith', 'Jane Doe']);
        $this->scrubber->setDomains(['acme.com']);
        $this->scrubber->setExtraPatterns([]);
    }

    public function test_html_email_with_signature(): void
    {
        $html = <<<'HTML'
        <p>Hi there,</p>
        <p>I need help with my account &amp; billing.</p>
        <p>John Smith mentioned it to me.</p>
        <br>
        <p>Thanks,</p>
        <p>John Smith</p>
        HTML;

        $result = $this->scrubber->cleanBody($html);

        $this->assertStringContainsString('I need help with my account & billing.', $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('&amp;', $result);
        // Name in body is redacted
        $this->assertStringContainsString('[NAME]', $result);
        // "Thanks," is a sign-off so the trailing block is stripped
        $this->assertStringNotContainsString('Thanks,', $result);
    }

    public function test_gmail_quoted_thread(): void
    {
        $text = <<<'TEXT'
        I cannot log in to my account.

        On Mon, Sep 8, 2026 at 10:30 AM Support Agent wrote:
        > Hi, can you try resetting your password?
        > Let me know if that helps.
        TEXT;

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringContainsString('I cannot log in', $result);
        $this->assertStringNotContainsString('Support Agent', $result);
        $this->assertStringNotContainsString('resetting your password', $result);
    }

    public function test_outlook_original_message(): void
    {
        $text = <<<'TEXT'
        Please update my address.

        -----Original Message-----
        From: support@example.com
        Sent: Monday, September 8, 2026
        Subject: Your ticket

        We received your request.
        TEXT;

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringContainsString('Please update my address.', $result);
        $this->assertStringNotContainsString('We received your request', $result);
    }

    public function test_irish_mobile_numbers(): void
    {
        $text = 'Call me on 087 123 4567 or +353 87 123 4567 please.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('087 123 4567', $result);
        $this->assertStringNotContainsString('+353 87 123 4567', $result);
        $this->assertStringContainsString('[PHONE]', $result);
    }

    public function test_iban_redaction(): void
    {
        $text = 'My IBAN is IE29 AIBK 9311 5212 3456 78 for the refund.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('IE29', $result);
        $this->assertStringContainsString('[IBAN]', $result);
    }

    public function test_url_with_token(): void
    {
        $text = 'See https://app.example.com/verify?token=abc123def456 for details.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('token=abc123', $result);
        $this->assertStringContainsString('[URL]', $result);
    }

    public function test_configured_domain_redaction(): void
    {
        $text = 'Visit acme.com for more info.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('acme.com', $result);
        $this->assertStringContainsString('[URL]', $result);
    }

    public function test_agent_name_from_config(): void
    {
        $text = 'I spoke with Jane Doe about this last week.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('Jane Doe', $result);
        $this->assertStringContainsString('[NAME]', $result);
    }

    public function test_email_redaction(): void
    {
        $text = 'Contact me at user@example.com please.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('user@example.com', $result);
        $this->assertStringContainsString('[EMAIL]', $result);
    }

    public function test_subject_prefix_stripping(): void
    {
        $this->assertEquals('Help needed', $this->scrubber->cleanSubject('Re: Fwd: Re: Help needed'));
        $this->assertEquals('Help needed', $this->scrubber->cleanSubject('FW: Help needed'));
    }

    public function test_french_quoted_reply(): void
    {
        $text = <<<'TEXT'
        Merci pour votre aide.

        Le 8 sept. 2026 à 10:30, support@example.com a écrit :
        > Bonjour, comment puis-je vous aider?
        TEXT;

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringContainsString('Merci', $result);
        $this->assertStringNotContainsString('Bonjour', $result);
    }

    public function test_sent_from_my_signature(): void
    {
        $text = <<<'TEXT'
        Can you check my order?

        Sent from my iPhone
        TEXT;

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringContainsString('Can you check my order?', $result);
        $this->assertStringNotContainsString('Sent from my iPhone', $result);
    }

    public function test_card_number_redaction(): void
    {
        $text = 'My card number is 4111 1111 1111 1111.';

        $result = $this->scrubber->cleanBody($text);

        $this->assertStringNotContainsString('4111', $result);
        $this->assertStringContainsString('[CARD]', $result);
    }

    public function test_extra_patterns(): void
    {
        $scrubber = new TextScrubber;
        $scrubber->setNames([]);
        $scrubber->setDomains([]);
        $scrubber->setExtraPatterns([
            ['pattern' => '/\bACME\b/i', 'token' => '[ORG]'],
        ]);

        $result = $scrubber->cleanBody('I work at ACME Corp.');

        $this->assertStringContainsString('[ORG]', $result);
        $this->assertStringNotContainsString('ACME', $result);
    }
}
