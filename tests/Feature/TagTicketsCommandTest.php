<?php

namespace Tests\Feature;

use App\Models\AiTaggingConfig;
use App\Models\Tag;
use App\Models\Ticket;
use App\Services\AiTagging\AiTagger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTicketsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'subject' => 'Test ticket',
            'requester_name' => 'Test User',
            'requester_email' => 'test@example.com',
        ], $overrides));
    }

    public function test_exits_gracefully_when_not_configured(): void
    {
        $this->artisan('support:tag-tickets')
            ->expectsOutputToContain('not configured')
            ->assertExitCode(0);
    }

    public function test_exits_gracefully_when_is_enabled_is_false(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => false,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'confidence_threshold' => 0.700,
        ]);

        $this->artisan('support:tag-tickets')
            ->expectsOutputToContain('not configured')
            ->assertExitCode(0);
    }

    public function test_exits_when_no_tickets_to_process(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Test', 'slug' => 'test']);

        $this->artisan('support:tag-tickets')
            ->expectsOutputToContain('No tickets to process')
            ->assertExitCode(0);
    }

    public function test_processes_untagged_open_tickets(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => false,
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);

        $open = $this->makeTicket(['subject' => 'Open ticket', 'status' => 'open']);
        $pending = $this->makeTicket(['subject' => 'Pending ticket', 'status' => 'pending']);
        $closed = $this->makeTicket(['subject' => 'Closed ticket', 'status' => 'closed']);

        // Mock the tagger to succeed
        $mock = $this->createMock(AiTagger::class);
        $mock->method('tagTicket')->willReturn(true);
        $this->app->instance(AiTagger::class, $mock);

        $this->artisan('support:tag-tickets')
            ->expectsOutputToContain('Processing 2 tickets')
            ->assertExitCode(0);
    }

    public function test_skips_already_processed_tickets(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => false,
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);

        // Already processed ticket
        $this->makeTicket(['ai_processed_at' => now()]);
        // Unprocessed ticket
        $this->makeTicket(['subject' => 'New one']);

        $mock = $this->createMock(AiTagger::class);
        $mock->method('tagTicket')->willReturn(true);
        $this->app->instance(AiTagger::class, $mock);

        $this->artisan('support:tag-tickets')
            ->expectsOutputToContain('Processing 1 tickets')
            ->assertExitCode(0);
    }

    public function test_reprocess_flag_includes_already_processed(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => false,
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);

        $this->makeTicket(['ai_processed_at' => now()]);
        $this->makeTicket(['subject' => 'New one']);

        $mock = $this->createMock(AiTagger::class);
        $mock->method('tagTicket')->willReturn(true);
        $this->app->instance(AiTagger::class, $mock);

        $this->artisan('support:tag-tickets --reprocess')
            ->expectsOutputToContain('Processing 2 tickets')
            ->assertExitCode(0);
    }

    public function test_respects_limit_option(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => false,
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);

        for ($i = 0; $i < 5; $i++) {
            $this->makeTicket(['subject' => "Ticket {$i}"]);
        }

        $mock = $this->createMock(AiTagger::class);
        $mock->method('tagTicket')->willReturn(true);
        $this->app->instance(AiTagger::class, $mock);

        $this->artisan('support:tag-tickets --limit=2')
            ->expectsOutputToContain('Processing 2 tickets')
            ->assertExitCode(0);
    }

    public function test_reports_failed_tickets(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => false,
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Billing', 'slug' => 'billing']);

        $this->makeTicket();

        $mock = $this->createMock(AiTagger::class);
        $mock->method('tagTicket')->willReturn(false);
        $this->app->instance(AiTagger::class, $mock);

        $this->artisan('support:tag-tickets')
            ->expectsOutputToContain('Failed: 1')
            ->assertExitCode(0);
    }
}
