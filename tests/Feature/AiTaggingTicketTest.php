<?php

namespace Tests\Feature;

use App\Jobs\TagTicketWithAi;
use App\Models\AiTaggingConfig;
use App\Models\Role;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiTaggingTicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);
    }

    protected function makeTicket(array $overrides = []): Ticket
    {
        return Ticket::create(array_merge([
            'subject' => 'Test ticket',
            'requester_name' => 'Test User',
            'requester_email' => 'test@example.com',
        ], $overrides));
    }

    // --- Job dispatch on ticket creation ---

    public function test_dispatches_job_when_ai_tagging_enabled(): void
    {
        Queue::fake();

        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => true,
            'confidence_threshold' => 0.700,
        ]);

        Tag::create(['name' => 'Test', 'slug' => 'test']);

        $this->actingAs($this->admin)
            ->post(route('tickets.store'), [
                'subject' => 'AI tagged ticket',
                'requester_name' => 'Customer',
                'requester_email' => 'customer@example.com',
            ]);

        Queue::assertPushed(TagTicketWithAi::class);
    }

    public function test_does_not_dispatch_job_when_no_config(): void
    {
        Queue::fake();

        // No AI config exists — system is opt-in
        $this->actingAs($this->admin)
            ->post(route('tickets.store'), [
                'subject' => 'Normal ticket',
                'requester_name' => 'Customer',
                'requester_email' => 'customer@example.com',
            ]);

        Queue::assertNotPushed(TagTicketWithAi::class);
    }

    public function test_does_not_dispatch_job_when_is_enabled_is_false(): void
    {
        Queue::fake();

        AiTaggingConfig::create([
            'is_enabled' => false,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => true,
            'confidence_threshold' => 0.700,
        ]);

        $this->actingAs($this->admin)
            ->post(route('tickets.store'), [
                'subject' => 'Disabled AI ticket',
                'requester_name' => 'Customer',
                'requester_email' => 'customer@example.com',
            ]);

        Queue::assertNotPushed(TagTicketWithAi::class);
    }

    public function test_does_not_dispatch_when_tag_on_create_is_off(): void
    {
        Queue::fake();

        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'tag_on_create' => false,
            'confidence_threshold' => 0.700,
        ]);

        $this->actingAs($this->admin)
            ->post(route('tickets.store'), [
                'subject' => 'Batch only ticket',
                'requester_name' => 'Customer',
                'requester_email' => 'customer@example.com',
            ]);

        Queue::assertNotPushed(TagTicketWithAi::class);
    }

    // --- Tag confirm/remove/dismiss actions ---

    public function test_agent_can_confirm_tag(): void
    {
        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();
        $ticket->tags()->attach($tag->id, [
            'confidence' => 0.90,
            'is_ai_suggested' => true,
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->admin)
            ->post(route('tickets.tags.confirm', [$ticket, $tag]))
            ->assertRedirect();

        $pivot = $ticket->tags()->where('tag_id', $tag->id)->first()->pivot;
        $this->assertTrue((bool) $pivot->is_confirmed);
    }

    public function test_agent_can_remove_tag(): void
    {
        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket();
        $ticket->tags()->attach($tag->id, [
            'confidence' => 0.90,
            'is_ai_suggested' => true,
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('tickets.tags.remove', [$ticket, $tag]))
            ->assertRedirect();

        $this->assertEquals(0, $ticket->tags()->count());
    }

    public function test_agent_can_dismiss_flag(): void
    {
        $ticket = $this->makeTicket([
            'ai_flagged' => true,
            'ai_flag_reason' => 'Wrong team assignment',
        ]);

        $this->actingAs($this->admin)
            ->post(route('tickets.dismiss-flag', $ticket))
            ->assertRedirect();

        $ticket->refresh();
        $this->assertFalse($ticket->ai_flagged);
        $this->assertNull($ticket->ai_flag_reason);
    }

    // --- Ticket views show AI data ---

    public function test_show_page_includes_tags(): void
    {
        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing', 'color' => '#ef4444']);
        $ticket = $this->makeTicket();
        $ticket->tags()->attach($tag->id, [
            'confidence' => 0.92,
            'is_ai_suggested' => true,
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Show')
                ->has('ticket.tags', 1)
            );
    }

    public function test_show_page_includes_flag_data(): void
    {
        $ticket = $this->makeTicket([
            'ai_flagged' => true,
            'ai_flag_reason' => 'Should be in IT Support',
        ]);

        $this->actingAs($this->admin)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ticket.ai_flagged', true)
                ->where('ticket.ai_flag_reason', 'Should be in IT Support')
            );
    }

    public function test_index_includes_tags_and_flag(): void
    {
        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        $ticket = $this->makeTicket(['ai_flagged' => true]);
        $ticket->tags()->attach($tag->id, [
            'confidence' => 0.90,
            'is_ai_suggested' => true,
            'is_confirmed' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tickets.data', 1)
                ->where('tickets.data.0.ai_flagged', true)
                ->has('tickets.data.0.tags', 1)
            );
    }

    // --- Actions require auth ---

    public function test_confirm_tag_requires_auth(): void
    {
        $tag = Tag::create(['name' => 'Test', 'slug' => 'test']);
        $ticket = $this->makeTicket();

        $this->post(route('tickets.tags.confirm', [$ticket, $tag]))
            ->assertRedirect(route('login'));
    }

    public function test_dismiss_flag_requires_auth(): void
    {
        $ticket = $this->makeTicket(['ai_flagged' => true]);

        $this->post(route('tickets.dismiss-flag', $ticket))
            ->assertRedirect(route('login'));
    }
}
