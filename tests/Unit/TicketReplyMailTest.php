<?php

namespace Tests\Unit;

use App\Mail\TicketReply;
use App\Models\Comment;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketReplyMailTest extends TestCase
{
    use RefreshDatabase;

    protected Ticket $ticket;

    protected Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ticket = Ticket::create([
            'subject' => 'Test ticket',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'reference' => 'REF-001',
        ]);

        $agent = User::factory()->create(['name' => 'Agent Smith']);

        $this->comment = $this->ticket->comments()->create([
            'body' => '<p>Here is your answer.</p>',
            'type' => 'reply',
            'is_internal' => false,
            'user_id' => $agent->id,
        ]);
    }

    public function test_notification_mode_does_not_include_reply_body(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'reply_email_mode' => 'notification',
        ]);

        $mail = new TicketReply($this->ticket, $this->comment, $tenant);
        $rendered = $mail->build()->render();

        $this->assertStringContainsString('A reply has been added to your ticket', $rendered);
        $this->assertStringContainsString('REF-001', $rendered);
        $this->assertStringNotContainsString('Here is your answer', $rendered);
    }

    public function test_full_mode_includes_reply_body(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'reply_email_mode' => 'full',
        ]);

        $mail = new TicketReply($this->ticket, $this->comment, $tenant);
        $rendered = $mail->build()->render();

        $this->assertStringContainsString('Here is your answer', $rendered);
        $this->assertStringContainsString('Agent Smith', $rendered);
        $this->assertStringContainsString('REF-001', $rendered);
    }

    public function test_full_mode_includes_attachment_links(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'reply_email_mode' => 'full',
        ]);

        $this->comment->attachments()->create([
            'filename' => 'abc123.pdf',
            'original_filename' => 'report.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1048576,
            'path' => 'attachments/test/abc123.pdf',
        ]);

        $mail = new TicketReply($this->ticket, $this->comment, $tenant);
        $rendered = $mail->build()->render();

        $this->assertStringContainsString('Attachments:', $rendered);
        $this->assertStringContainsString('report.pdf', $rendered);
        $this->assertStringContainsString('1 MB', $rendered);
    }

    public function test_notification_mode_does_not_include_attachments(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'reply_email_mode' => 'notification',
        ]);

        $this->comment->attachments()->create([
            'filename' => 'abc123.pdf',
            'original_filename' => 'report.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1048576,
            'path' => 'attachments/test/abc123.pdf',
        ]);

        $mail = new TicketReply($this->ticket, $this->comment, $tenant);
        $rendered = $mail->build()->render();

        $this->assertStringNotContainsString('Attachments:', $rendered);
        $this->assertStringNotContainsString('report.pdf', $rendered);
    }

    public function test_defaults_to_notification_mode_when_no_tenant(): void
    {
        $mail = new TicketReply($this->ticket, $this->comment, null);
        $rendered = $mail->build()->render();

        $this->assertStringContainsString('A reply has been added to your ticket', $rendered);
        $this->assertStringNotContainsString('Here is your answer', $rendered);
    }

    public function test_envelope_uses_tenant_support_email(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Support',
            'slug' => 'acme',
            'support_email' => 'help@acme.com',
        ]);

        $mail = new TicketReply($this->ticket, $this->comment, $tenant);
        $envelope = $mail->envelope();

        $this->assertEquals('help@acme.com', $envelope->from->address);
        $this->assertEquals('Acme Support', $envelope->from->name);
    }

    public function test_envelope_uses_default_when_no_tenant(): void
    {
        $mail = new TicketReply($this->ticket, $this->comment, null);
        $envelope = $mail->envelope();

        $this->assertEquals(config('mail.from.address'), $envelope->from->address);
    }

    public function test_subject_includes_ticket_reference(): void
    {
        $mail = new TicketReply($this->ticket, $this->comment, null);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('[REF-001]', $envelope->subject);
        $this->assertStringContainsString('Test ticket', $envelope->subject);
    }

    public function test_full_mode_uses_sanitized_body(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'reply_email_mode' => 'full',
        ]);

        $this->comment->update(['body' => '<p>Safe</p><script>alert("xss")</script>']);

        $mail = new TicketReply($this->ticket, $this->comment->fresh(), $tenant);
        $rendered = $mail->build()->render();

        $this->assertStringContainsString('Safe', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
    }

    public function test_portal_ticket_url_in_email(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test',
            'domain' => 'help.example.com',
            'reply_email_mode' => 'full',
        ]);

        $mail = new TicketReply($this->ticket, $this->comment, $tenant);
        $rendered = $mail->build()->render();

        $this->assertStringContainsString("/portal/tickets/{$this->ticket->id}", $rendered);
    }
}
