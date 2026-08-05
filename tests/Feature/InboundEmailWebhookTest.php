<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundEmailJob;
use App\Models\InboundEmail;
use App\Models\Ticket;
use App\Services\InboundEmailProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class InboundEmailWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['support.inbound.webhook_secret' => $this->secret]);
    }

    /**
     * Sign and post with the timestamp-inclusive HMAC scheme.
     * Signature = HMAC-SHA256(timestamp.body, secret)
     */
    protected function signedPost(array $data, ?string $secret = null, ?int $timestamp = null): TestResponse
    {
        $payload = json_encode($data);
        $ts = (string) ($timestamp ?? time());
        $signedPayload = $ts.'.'.$payload;
        $sig = hash_hmac('sha256', $signedPayload, $secret ?? $this->secret);

        return $this->call('POST', route('inbound.email'), [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            'HTTP_X_WEBHOOK_TIMESTAMP' => $ts,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    public function test_stores_payload_and_dispatches_job(): void
    {
        Queue::fake();

        $this->signedPost([
            'message_id' => 'msg-001@example.com',
            'from_email' => 'customer@example.com',
            'from_name' => 'Customer',
            'subject' => 'Help needed',
            'body' => '<p>Please help</p>',
        ])->assertOk()
            ->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('inbound_emails', [
            'message_id' => 'msg-001@example.com',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessInboundEmailJob::class);
    }

    public function test_duplicate_delivery_returns_200_without_reprocessing(): void
    {
        Queue::fake();

        $data = [
            'message_id' => 'msg-dup@example.com',
            'from_email' => 'customer@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ];

        $this->signedPost($data)->assertOk()->assertJson(['status' => 'queued']);
        $this->signedPost($data)->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertEquals(1, InboundEmail::where('message_id', 'msg-dup@example.com')->count());
        Queue::assertPushed(ProcessInboundEmailJob::class, 1);
    }

    public function test_rejects_replayed_timestamp(): void
    {
        $this->signedPost([
            'message_id' => 'msg-old@example.com',
            'from_email' => 'customer@example.com',
            'subject' => 'Old',
            'body' => 'Body',
        ], timestamp: time() - 600) // 10 minutes ago
            ->assertUnauthorized()
            ->assertJson(['error' => 'Request expired']);

        $this->assertEquals(0, InboundEmail::count());
    }

    public function test_rejects_without_signature(): void
    {
        $this->postJson(route('inbound.email'), [
            'message_id' => 'msg-nosig@example.com',
            'from_email' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ])->assertUnauthorized();
    }

    public function test_rejects_invalid_signature(): void
    {
        $this->signedPost([
            'message_id' => 'msg-badsig@example.com',
            'from_email' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ], 'wrong-secret')->assertUnauthorized();
    }

    public function test_rejects_when_no_secret_configured(): void
    {
        config(['support.inbound.webhook_secret' => null]);

        $this->postJson(route('inbound.email'), [
            'from_email' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ], [
            'X-Webhook-Signature' => 'anything',
            'X-Webhook-Timestamp' => (string) time(),
        ])->assertUnauthorized();
    }

    public function test_job_creates_ticket_when_processed(): void
    {
        $record = InboundEmail::create([
            'message_id' => 'msg-job@example.com',
            'payload' => [
                'message_id' => 'msg-job@example.com',
                'from_email' => 'customer@example.com',
                'from_name' => 'Customer',
                'subject' => 'New issue',
                'body' => '<p>Help</p>',
            ],
            'status' => 'pending',
        ]);

        (new ProcessInboundEmailJob($record->id))->handle(app(InboundEmailProcessor::class));

        $record->refresh();
        $this->assertEquals('processed', $record->status);
        $this->assertNotNull($record->processed_at);

        $this->assertDatabaseHas('tickets', [
            'subject' => 'New issue',
            'requester_email' => 'customer@example.com',
            'source' => 'email',
        ]);
    }

    public function test_job_threads_reply(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Existing',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000001']);

        $record = InboundEmail::create([
            'message_id' => 'msg-reply@example.com',
            'payload' => [
                'message_id' => 'msg-reply@example.com',
                'from_email' => 'customer@example.com',
                'subject' => 'Re: [TKT-000001] Existing',
                'body' => 'Follow up',
            ],
            'status' => 'pending',
        ]);

        (new ProcessInboundEmailJob($record->id))->handle(app(InboundEmailProcessor::class));

        $this->assertEquals('processed', $record->fresh()->status);
        $this->assertEquals(1, $ticket->comments()->count());
        $this->assertEquals(1, Ticket::count());
    }

    public function test_job_rejects_spam(): void
    {
        config(['support.spam.blocklist' => ['spam.com']]);

        $record = InboundEmail::create([
            'message_id' => 'msg-spam@spam.com',
            'payload' => [
                'message_id' => 'msg-spam@spam.com',
                'from_email' => 'user@spam.com',
                'subject' => 'Buy now',
                'body' => 'Spam',
            ],
            'status' => 'pending',
        ]);

        (new ProcessInboundEmailJob($record->id))->handle(app(InboundEmailProcessor::class));

        $this->assertEquals('rejected', $record->fresh()->status);
        $this->assertEquals(0, Ticket::count());
    }

    public function test_concurrent_duplicate_handled_by_unique_constraint(): void
    {
        Queue::fake();

        InboundEmail::create([
            'message_id' => 'msg-race@example.com',
            'payload' => ['from_email' => 'a@test.com', 'subject' => 'Race', 'body' => 'B'],
            'status' => 'pending',
        ]);

        $this->signedPost([
            'message_id' => 'msg-race@example.com',
            'from_email' => 'a@test.com',
            'subject' => 'Race',
            'body' => 'B',
        ])->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertEquals(1, InboundEmail::where('message_id', 'msg-race@example.com')->count());
    }

    // Item 4: Timestamp-inclusive signature tests

    public function test_rejects_when_timestamp_altered_after_signing(): void
    {
        $data = [
            'message_id' => 'msg-tamper@example.com',
            'from_email' => 'test@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ];

        $originalTs = time();
        $payload = json_encode($data);
        // Sign with original timestamp
        $sig = hash_hmac('sha256', $originalTs.'.'.$payload, $this->secret);

        // Submit with a different (fresh) timestamp — signature should not match
        $freshTs = $originalTs + 1;

        $this->call('POST', route('inbound.email'), [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $freshTs,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertUnauthorized();
    }

    public function test_rejects_missing_timestamp_header(): void
    {
        $payload = json_encode(['from_email' => 'a@b.com', 'subject' => 'X', 'body' => 'Y']);
        $sig = hash_hmac('sha256', $payload, $this->secret);

        $this->call('POST', route('inbound.email'), [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertUnauthorized()
            ->assertJson(['error' => 'Missing or invalid timestamp']);
    }

    public function test_rejects_non_numeric_timestamp(): void
    {
        $payload = json_encode(['from_email' => 'a@b.com', 'subject' => 'X', 'body' => 'Y']);

        $this->call('POST', route('inbound.email'), [], [], [], [
            'HTTP_X_WEBHOOK_SIGNATURE' => 'anything',
            'HTTP_X_WEBHOOK_TIMESTAMP' => 'not-a-number',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertUnauthorized()
            ->assertJson(['error' => 'Missing or invalid timestamp']);
    }

    public function test_correctly_signed_request_inside_window_accepted(): void
    {
        Queue::fake();

        $this->signedPost([
            'message_id' => 'msg-valid@example.com',
            'from_email' => 'customer@example.com',
            'subject' => 'Valid',
            'body' => 'Body',
        ], timestamp: time())->assertOk()
            ->assertJson(['status' => 'queued']);
    }
}
