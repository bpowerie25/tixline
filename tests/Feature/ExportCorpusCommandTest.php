<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Label;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportCorpusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $outPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outPath = storage_path('app/exports/test-corpus.jsonl');

        if (file_exists($this->outPath)) {
            unlink($this->outPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->outPath)) {
            unlink($this->outPath);
        }

        $readme = dirname($this->outPath).'/README.md';
        if (file_exists($readme)) {
            unlink($readme);
        }

        parent::tearDown();
    }

    public function test_exports_one_row_per_ticket_by_default(): void
    {
        $agent = User::factory()->create([
            'name' => 'Agent One',
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);

        // Ticket 1: customer message + agent reply + private note
        $t1 = Ticket::create([
            'subject' => 'Help with login',
            'body' => '<p>I cannot log in to my account.</p>',
            'requester_name' => 'Alice',
            'requester_email' => 'alice@example.com',
            'status' => 'open',
            'source' => 'email',
        ]);

        Comment::create([
            'ticket_id' => $t1->id,
            'user_id' => $agent->id,
            'body' => 'Have you tried resetting your password?',
            'is_internal' => false,
            'type' => 'reply',
        ]);

        Comment::create([
            'ticket_id' => $t1->id,
            'user_id' => $agent->id,
            'body' => 'Internal: escalate to tier 2',
            'is_internal' => true,
            'type' => 'note',
        ]);

        // Ticket 2: customer message only
        $t2 = Ticket::create([
            'subject' => 'Billing question',
            'body' => 'I was charged twice.',
            'requester_name' => 'Bob',
            'requester_email' => 'bob@example.com',
            'status' => 'pending',
            'source' => 'web',
        ]);

        // Ticket 3: customer message with a customer reply
        $t3 = Ticket::create([
            'subject' => 'Feature request',
            'body' => 'Can you add dark mode?',
            'requester_name' => 'Alice',
            'requester_email' => 'alice@example.com',
            'status' => 'resolved',
            'source' => 'web',
        ]);

        Comment::create([
            'ticket_id' => $t3->id,
            'user_id' => null,
            'body' => 'Also night mode for the mobile app.',
            'is_internal' => false,
            'type' => 'reply',
        ]);

        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'test-salt-123',
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $lines = array_filter(explode("\n", file_get_contents($this->outPath)));
        $this->assertCount(3, $lines, 'Default mode: one row per ticket');

        // Verify no agent text or note text
        $content = file_get_contents($this->outPath);
        $this->assertStringNotContainsString('resetting your password', $content);
        $this->assertStringNotContainsString('escalate to tier 2', $content);

        // Verify row structure
        $row = json_decode($lines[0], true);
        $this->assertArrayHasKey('ticket_id', $row);
        $this->assertArrayHasKey('created_at', $row);
        $this->assertArrayHasKey('status', $row);
        $this->assertArrayHasKey('channel', $row);
        $this->assertArrayHasKey('subject', $row);
        $this->assertArrayHasKey('body', $row);
        $this->assertArrayHasKey('requester_hash', $row);
        $this->assertArrayHasKey('tags', $row);
        $this->assertArrayNotHasKey('message_index', $row);
    }

    public function test_include_replies_adds_customer_messages_only(): void
    {
        $agent = User::factory()->create([
            'name' => 'Agent One',
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);

        $t1 = Ticket::create([
            'subject' => 'Help me',
            'body' => 'First customer message.',
            'requester_name' => 'Alice',
            'requester_email' => 'alice@example.com',
            'status' => 'open',
            'source' => 'email',
        ]);

        // Agent reply — should NOT appear
        Comment::create([
            'ticket_id' => $t1->id,
            'user_id' => $agent->id,
            'body' => 'Agent reply text here.',
            'is_internal' => false,
            'type' => 'reply',
        ]);

        // Customer follow-up — should appear
        Comment::create([
            'ticket_id' => $t1->id,
            'user_id' => null,
            'body' => 'Customer follow-up.',
            'is_internal' => false,
            'type' => 'reply',
        ]);

        // Private note — should NOT appear
        Comment::create([
            'ticket_id' => $t1->id,
            'user_id' => $agent->id,
            'body' => 'Private note.',
            'is_internal' => true,
            'type' => 'note',
        ]);

        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'test-salt-123',
            '--include-replies' => true,
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $lines = array_filter(explode("\n", file_get_contents($this->outPath)));
        $this->assertCount(2, $lines, 'Ticket body + 1 customer reply = 2 rows');

        $content = file_get_contents($this->outPath);
        $this->assertStringNotContainsString('Agent reply text here', $content);
        $this->assertStringNotContainsString('Private note', $content);

        $row0 = json_decode($lines[0], true);
        $row1 = json_decode($lines[1], true);
        $this->assertEquals(0, $row0['message_index']);
        $this->assertEquals(1, $row1['message_index']);
    }

    public function test_requester_hash_is_stable_within_run_and_differs_across_salts(): void
    {
        Ticket::create([
            'subject' => 'T1',
            'body' => 'Body',
            'requester_name' => 'Alice',
            'requester_email' => 'alice@example.com',
            'status' => 'open',
            'source' => 'web',
        ]);

        Ticket::create([
            'subject' => 'T2',
            'body' => 'Body',
            'requester_name' => 'Alice Again',
            'requester_email' => 'alice@example.com',
            'status' => 'open',
            'source' => 'web',
        ]);

        // Export with salt A
        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'salt-A',
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $lines = array_filter(explode("\n", file_get_contents($this->outPath)));
        $rowA1 = json_decode($lines[0], true);
        $rowA2 = json_decode($lines[1], true);

        // Same email → same hash within a run
        $this->assertEquals($rowA1['requester_hash'], $rowA2['requester_hash']);

        // Export with salt B
        unlink($this->outPath);
        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'salt-B',
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $linesB = array_filter(explode("\n", file_get_contents($this->outPath)));
        $rowB1 = json_decode($linesB[0], true);

        // Different salt → different hash
        $this->assertNotEquals($rowA1['requester_hash'], $rowB1['requester_hash']);
    }

    public function test_audit_command_passes_on_clean_export(): void
    {
        Ticket::create([
            'subject' => 'Test ticket',
            'body' => 'I need help with my order.',
            'requester_name' => 'Alice',
            'requester_email' => 'alice@example.com',
            'status' => 'open',
            'source' => 'web',
        ]);

        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'test-salt',
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $this->artisan('tickets:audit-corpus', [
            'file' => $this->outPath,
        ])->assertSuccessful();
    }

    public function test_labels_appear_as_tags(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Tagged ticket',
            'body' => 'Body text.',
            'requester_name' => 'Alice',
            'requester_email' => 'alice@example.com',
            'status' => 'open',
            'source' => 'email',
        ]);

        $label = Label::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket->labels()->attach($label);

        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'test-salt',
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $lines = array_filter(explode("\n", file_get_contents($this->outPath)));
        $row = json_decode($lines[0], true);
        $this->assertEquals(['Billing'], $row['tags']);
    }

    public function test_readme_is_generated(): void
    {
        Ticket::create([
            'subject' => 'T',
            'body' => 'B',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'status' => 'open',
            'source' => 'web',
        ]);

        $this->artisan('tickets:export-corpus', [
            '--out' => $this->outPath,
            '--salt' => 'test-salt',
            '--since' => '2020-01-01',
        ])->assertSuccessful();

        $readmePath = dirname($this->outPath).'/README.md';
        $this->assertFileExists($readmePath);
        $this->assertStringContainsString('Data Dictionary', file_get_contents($readmePath));
    }
}
