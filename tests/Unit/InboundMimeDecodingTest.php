<?php

namespace Tests\Unit;

use App\DTOs\InboundMessage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\InboundEmailProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InboundMimeDecodingTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_payload_decodes_mime_encoded_name(): void
    {
        $message = InboundMessage::fromWebhookPayload([
            'message_id' => 'test-1',
            'from_email' => 'marek@example.com',
            'from_name' => '=?utf-8?Q?Marek_M=C3=BChlberg?=',
            'subject' => 'Help',
            'body' => 'Test',
        ]);

        $this->assertEquals('Marek Mühlberg', $message->fromName);
    }

    public function test_raw_email_decodes_mime_encoded_name(): void
    {
        $raw = "From: =?utf-8?Q?Marek_M=C3=BChlberg?= <marek@example.com>\r\n"
            . "Subject: Test\r\n"
            . "Content-Type: text/plain\r\n"
            . "\r\n"
            . "Hello";

        $message = InboundMessage::fromRawEmail($raw);

        $this->assertEquals('Marek Mühlberg', $message->fromName);
    }

    public function test_plain_ascii_name_unchanged(): void
    {
        $message = InboundMessage::fromWebhookPayload([
            'message_id' => 'test-2',
            'from_email' => 'john@example.com',
            'from_name' => 'John Smith',
            'subject' => 'Help',
            'body' => 'Test',
        ]);

        $this->assertEquals('John Smith', $message->fromName);
    }

    public function test_processor_creates_customer_with_tenant_id(): void
    {
        $tenant = Tenant::create(['name' => 'Test', 'slug' => 'test']);
        app()->instance('tenant', $tenant);
        Cache::flush();

        $processor = app(InboundEmailProcessor::class);
        $message = new InboundMessage(
            messageId: 'test-tenant',
            fromEmail: 'newuser@example.com',
            fromName: 'New User',
            subject: 'First ticket',
            body: 'Hello',
        );

        $processor->process($message);

        $customer = Customer::withoutGlobalScopes()
            ->where('email', 'newuser@example.com')
            ->first();

        $this->assertNotNull($customer);
        $this->assertEquals($tenant->id, $customer->tenant_id);
    }

    public function test_processor_creates_customer_with_fallback_tenant_id(): void
    {
        // No tenant bound — should fall back to Tenant::first()
        $tenant = Tenant::create(['name' => 'Default', 'slug' => 'default']);
        app()->forgetInstance('tenant');
        Cache::flush();

        $processor = app(InboundEmailProcessor::class);
        $message = new InboundMessage(
            messageId: 'test-fallback',
            fromEmail: 'fallback@example.com',
            fromName: 'Fallback User',
            subject: 'Ticket without tenant',
            body: 'Hello',
        );

        $processor->process($message);

        $customer = Customer::withoutGlobalScopes()
            ->where('email', 'fallback@example.com')
            ->first();

        $this->assertNotNull($customer);
        $this->assertEquals($tenant->id, $customer->tenant_id);
    }
}
