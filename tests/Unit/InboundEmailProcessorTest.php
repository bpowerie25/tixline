<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Services\InboundEmailProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InboundEmailProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected InboundEmailProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = app(InboundEmailProcessor::class);
        Cache::flush();
    }

    public function test_creates_new_ticket(): void
    {
        $result = $this->processor->process(
            fromEmail: 'customer@example.com',
            fromName: 'Customer',
            subject: 'Help needed',
            body: '<p>Please help</p>',
        );

        $this->assertEquals('created', $result['status']);
        $this->assertNotNull($result['reference']);

        $ticket = Ticket::find($result['ticket_id']);
        $this->assertEquals('Help needed', $ticket->subject);
        $this->assertEquals('customer@example.com', $ticket->requester_email);
        $this->assertEquals('email', $ticket->source);
    }

    public function test_threads_reply_by_reference(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Original issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'source' => 'email',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000001']);

        $result = $this->processor->process(
            fromEmail: 'customer@example.com',
            fromName: 'Customer',
            subject: 'Re: [TKT-000001] Original issue',
            body: '<p>Following up</p>',
        );

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
        $this->assertEquals(1, $ticket->comments()->count());
    }

    public function test_threads_reply_by_subject_match(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Login problem',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'source' => 'email',
            'status' => 'open',
        ]);

        $result = $this->processor->process(
            fromEmail: 'customer@example.com',
            fromName: 'Customer',
            subject: 'Re: Login problem',
            body: '<p>Still broken</p>',
        );

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
    }

    public function test_reply_reopens_resolved_ticket(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Fixed issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'source' => 'email',
            'status' => 'resolved',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000002']);

        $this->processor->process(
            fromEmail: 'customer@example.com',
            fromName: 'Customer',
            subject: 'Re: [TKT-000002] Fixed issue',
            body: '<p>Actually not fixed</p>',
        );

        $this->assertEquals('open', $ticket->fresh()->status);
    }

    public function test_rejects_spam(): void
    {
        config(['support.spam.blocklist' => ['spam.com']]);

        $result = $this->processor->process(
            fromEmail: 'user@spam.com',
            fromName: 'Spammer',
            subject: 'Buy now',
            body: 'Spam',
        );

        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals('blocklisted', $result['reason']);
        $this->assertEquals(0, Ticket::count());
    }

    public function test_empty_subject_gets_default(): void
    {
        $result = $this->processor->process(
            fromEmail: 'user@example.com',
            fromName: 'User',
            subject: '',
            body: 'No subject email',
        );

        $this->assertEquals('created', $result['status']);
        $ticket = Ticket::find($result['ticket_id']);
        $this->assertEquals('(No Subject)', $ticket->subject);
    }

    public function test_does_not_thread_to_closed_ticket(): void
    {
        Ticket::create([
            'subject' => 'Old issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'source' => 'email',
            'status' => 'closed',
        ]);

        $result = $this->processor->process(
            fromEmail: 'customer@example.com',
            fromName: 'Customer',
            subject: 'Re: Old issue',
            body: 'New question',
        );

        // Should create a new ticket, not thread to the closed one
        $this->assertEquals('created', $result['status']);
        $this->assertEquals(2, Ticket::count());
    }
}
