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
            authResults: $overrides['authResults'] ?? null,
        );
    }

    protected function passingAuth(): array
    {
        return ['spf' => 'pass', 'dkim' => 'pass', 'dmarc' => 'pass'];
    }

    protected function failingAuth(): array
    {
        return ['spf' => 'fail', 'dkim' => 'fail', 'dmarc' => 'fail'];
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

    public function test_reference_hijack_creates_new_ticket(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Private issue',
            'requester_name' => 'Real Customer',
            'requester_email' => 'real@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000099']);

        // Attacker knows the ticket reference but is not the requester
        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'attacker@evil.com',
            'subject' => 'Re: [TKT-000099] Private issue',
            'body' => 'I am injecting into your ticket',
        ]));

        // Should create a NEW ticket, not append to the existing one
        $this->assertEquals('created', $result['status']);
        $this->assertNotEquals($ticket->id, $result['ticket_id']);
        $this->assertEquals(0, $ticket->comments()->count());
        $this->assertEquals(2, Ticket::count());
    }

    public function test_genuine_requester_threads_by_reference(): void
    {
        $ticket = Ticket::create([
            'subject' => 'My issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000098']);

        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'customer@example.com',
            'subject' => 'Re: [TKT-000098] My issue',
        ]));

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
    }

    public function test_known_agent_can_thread_by_reference_with_auth(): void
    {
        $agent = \App\Models\User::factory()->create(['role' => 'agent']);

        $ticket = Ticket::create([
            'subject' => 'Issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000097']);

        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => $agent->email,
            'subject' => 'Re: [TKT-000097] Issue',
            'authResults' => $this->passingAuth(),
        ]));

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
    }

    public function test_subject_fallback_requires_same_sender(): void
    {
        Ticket::create([
            'subject' => 'Login problem',
            'requester_name' => 'Real Customer',
            'requester_email' => 'real@example.com',
            'status' => 'open',
        ]);

        // Different sender, same subject — should create new ticket
        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'other@example.com',
            'subject' => 'Re: Login problem',
        ]));

        $this->assertEquals('created', $result['status']);
        $this->assertEquals(2, Ticket::count());
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

    // --- Email authentication gating tests ---

    public function test_spoofed_from_with_failing_auth_creates_new_ticket(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Secret issue',
            'requester_name' => 'Real',
            'requester_email' => 'real@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000050']);

        // Register a participant so the sender "looks" entitled by email
        $participant = \App\Models\User::factory()->create(['email' => 'participant@example.com']);
        $ticket->comments()->create([
            'body' => 'Earlier reply',
            'type' => 'reply',
            'user_id' => $participant->id,
        ]);

        // Spoofed message claiming participant address, but auth fails
        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'participant@example.com',
            'subject' => 'Re: [TKT-000050] Secret issue',
            'authResults' => $this->failingAuth(),
        ]));

        // Must create a new ticket, not append
        $this->assertEquals('created', $result['status']);
        $this->assertNotEquals($ticket->id, $result['ticket_id']);
        // Original ticket should still have only the one comment
        $this->assertEquals(1, $ticket->comments()->count());
    }

    public function test_authenticated_participant_can_thread(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Issue',
            'requester_name' => 'Real',
            'requester_email' => 'real@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000051']);

        $participant = \App\Models\User::factory()->create(['email' => 'participant@example.com']);
        $ticket->comments()->create([
            'body' => 'Earlier reply',
            'type' => 'reply',
            'user_id' => $participant->id,
        ]);

        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'participant@example.com',
            'subject' => 'Re: [TKT-000051] Issue',
            'authResults' => $this->passingAuth(),
        ]));

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
    }

    public function test_agent_address_with_failing_auth_never_gains_entitlement(): void
    {
        $agent = \App\Models\User::factory()->create(['role' => 'agent']);

        $ticket = Ticket::create([
            'subject' => 'Sensitive ticket',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000052']);

        // Attacker spoofs the agent's address but fails auth
        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => $agent->email,
            'subject' => 'Re: [TKT-000052] Sensitive ticket',
            'authResults' => $this->failingAuth(),
        ]));

        $this->assertEquals('created', $result['status']);
        $this->assertNotEquals($ticket->id, $result['ticket_id']);
        $this->assertEquals(0, $ticket->comments()->count());
    }

    public function test_agent_address_with_no_auth_results_never_gains_entitlement(): void
    {
        $agent = \App\Models\User::factory()->create(['role' => 'agent']);

        $ticket = Ticket::create([
            'subject' => 'Ticket',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000053']);

        // No auth results at all (e.g. pipe path)
        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => $agent->email,
            'subject' => 'Re: [TKT-000053] Ticket',
            'authResults' => null,
        ]));

        $this->assertEquals('created', $result['status']);
        $this->assertNotEquals($ticket->id, $result['ticket_id']);
    }

    public function test_original_requester_threads_without_auth_results(): void
    {
        // The requester already knows the reference — auth not required
        $ticket = Ticket::create([
            'subject' => 'My issue',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000054']);

        $result = $this->processor->process($this->makeMessage([
            'fromEmail' => 'customer@example.com',
            'subject' => 'Re: [TKT-000054] My issue',
            'authResults' => null,  // pipe path, no auth
        ]));

        $this->assertEquals('reply', $result['status']);
        $this->assertEquals($ticket->id, $result['ticket_id']);
    }

    public function test_auth_results_extracted_from_explicit_payload_fields(): void
    {
        $message = InboundMessage::fromWebhookPayload([
            'from_email' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body',
            'spf' => 'pass',
            'dkim' => 'pass',
            'dmarc' => 'fail',
        ]);

        $this->assertEquals('pass', $message->authResults['spf']);
        $this->assertEquals('pass', $message->authResults['dkim']);
        $this->assertEquals('fail', $message->authResults['dmarc']);
        $this->assertTrue($message->authPassed()); // spf+dkim pass
    }

    public function test_auth_results_extracted_from_authentication_results_header(): void
    {
        $message = InboundMessage::fromWebhookPayload([
            'from_email' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body',
            'headers' => [
                'authentication-results' => 'mx.google.com; dkim=pass; spf=pass; dmarc=pass',
            ],
        ]);

        $this->assertEquals('pass', $message->authResults['spf']);
        $this->assertEquals('pass', $message->authResults['dkim']);
        $this->assertEquals('pass', $message->authResults['dmarc']);
        $this->assertTrue($message->authPassed());
    }

    public function test_auth_passed_returns_false_when_all_fail(): void
    {
        $message = new InboundMessage(
            messageId: 'test',
            fromEmail: 'test@example.com',
            fromName: 'Test',
            subject: 'Test',
            body: 'Test',
            authResults: ['spf' => 'fail', 'dkim' => 'fail', 'dmarc' => 'fail'],
        );

        $this->assertFalse($message->authPassed());
    }

    public function test_auth_passed_returns_false_when_null(): void
    {
        $message = new InboundMessage(
            messageId: 'test',
            fromEmail: 'test@example.com',
            fromName: 'Test',
            subject: 'Test',
            body: 'Test',
            authResults: null,
        );

        $this->assertFalse($message->authPassed());
    }
}
