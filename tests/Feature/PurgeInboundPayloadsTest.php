<?php

namespace Tests\Feature;

use App\Models\InboundEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeInboundPayloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_old_payloads(): void
    {
        // Old record — should be purged
        $old = InboundEmail::create([
            'message_id' => 'old@example.com',
            'payload' => ['from_email' => 'old@test.com', 'body' => 'old data'],
            'status' => 'processed',
        ]);
        InboundEmail::where('id', $old->id)->update(['created_at' => now()->subDays(31)]);

        // Recent record — should be kept
        $recent = InboundEmail::create([
            'message_id' => 'recent@example.com',
            'payload' => ['from_email' => 'recent@test.com', 'body' => 'recent data'],
            'status' => 'processed',
        ]);

        $this->artisan('support:purge-payloads')->assertSuccessful();

        // Old payload nulled, row preserved
        $this->assertNull($old->fresh()->payload);
        $this->assertNotNull($old->fresh()->message_id);

        // Recent payload intact
        $this->assertNotNull($recent->fresh()->payload);
    }

    public function test_respects_custom_days_option(): void
    {
        $record = InboundEmail::create([
            'message_id' => 'test@example.com',
            'payload' => ['body' => 'data'],
            'status' => 'processed',
        ]);
        InboundEmail::where('id', $record->id)->update(['created_at' => now()->subDays(8)]);

        $this->artisan('support:purge-payloads', ['--days' => 7])
            ->assertSuccessful();

        $this->assertNull($record->fresh()->payload);
    }

    public function test_does_not_delete_rows(): void
    {
        $record = InboundEmail::create([
            'message_id' => 'keep-row@example.com',
            'payload' => ['body' => 'data'],
            'status' => 'processed',
        ]);
        InboundEmail::where('id', $record->id)->update(['created_at' => now()->subDays(31)]);

        $this->artisan('support:purge-payloads')->assertSuccessful();

        // Row must still exist for idempotency and audit
        $this->assertNotNull($record->fresh());
        $this->assertEquals('keep-row@example.com', $record->fresh()->message_id);
    }

    public function test_skips_already_nulled_payloads(): void
    {
        $record = InboundEmail::create([
            'message_id' => 'already-null@example.com',
            'payload' => ['temp' => 'data'],
            'status' => 'processed',
        ]);
        InboundEmail::where('id', $record->id)->update([
            'created_at' => now()->subDays(31),
            'payload' => null,
        ]);

        $this->artisan('support:purge-payloads')->assertSuccessful();

        // Confirm it wasn't touched (still null, row exists)
        $this->assertNull($record->fresh()->payload);
    }

    public function test_rejects_zero_day_retention(): void
    {
        $this->artisan('support:purge-payloads', ['--days' => 0])
            ->assertFailed();
    }

    public function test_config_default_is_30_days(): void
    {
        $this->assertEquals(30, config('support.inbound.payload_retention_days'));
    }
}
