<?php

namespace Tests\Unit;

use App\DTOs\InboundMessage;
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

    protected function makeMessage(array $overrides = []): InboundMessage
    {
        return new InboundMessage(
            messageId: $overrides['messageId'] ?? uniqid('test-'),
            fromEmail: $overrides['fromEmail'] ?? 'customer@example.com',
            fromName: $overrides['fromName'] ?? 'Customer',
            subject: $overrides['subject'] ?? 'Help needed',
            body: $overrides['body'] ?? '<p>Please help</p>',
            headers: $overrides['headers'] ?? [],
        );
    }

    public function test_creates_new_ticket(): void
    {
        $result = $this->processor->process($this->makeMessage());

        $this->assertEquals('created', $result['status']);
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
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000001']);

        $result = $this->processor->process($this->makeMessage([
            'subject' => 'Re: [TKT-000001] Original issue',
        ]));

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
            'status' => 'open',
        ]);

        $result = $this->processor->process($this->makeMessage([
            'subject' => 'Re: Login problem',
        ]));

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
    }

    public function test_reply_reopens_resolved_ticket(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Fixed issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'status' => 'resolved',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000002']);

        $this->processor->process($this->makeMessage([
            'subject' => 'Re: [TKT-000002] Fixed issue',
        ]));

        $this->assertEquals('open', $ticket->fresh()->status);
    }

    public function test_rejects_spam(): void
    {
        config(['support.spam.blocklist' => ['spam.com']]);

        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'user@spam.com',
        ]));

        $this->assertEquals('rejected', $result['status']);
        $this->assertEquals(0, Ticket::count());
    }

    public function test_empty_subject_gets_default(): void
    {
        $result = $this->processor->process($this->makeMessage(['subject' => '']));

        $ticket = Ticket::find($result['ticket_id']);
        $this->assertEquals('(No Subject)', $ticket->subject);
    }

    public function test_does_not_thread_to_closed_ticket(): void
    {
        Ticket::create([
            'subject' => 'Old issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
            'status' => 'closed',
        ]);

        $result = $this->processor->process($this->makeMessage([
            'subject' => 'Re: Old issue',
        ]));

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(2, Ticket::count());
    }
}
