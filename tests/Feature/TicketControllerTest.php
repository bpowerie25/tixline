<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'admin']);
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('tickets.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_tickets(): void
    {
        Ticket::create(['subject' => 'Test ticket', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tickets/Index')
                ->has('tickets.data', 1)
            );
    }

    public function test_index_filters_by_status(): void
    {
        Ticket::create(['subject' => 'Open', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'status' => 'open']);
        Ticket::create(['subject' => 'Closed', 'requester_name' => 'B', 'requester_email' => 'b@test.com', 'status' => 'closed']);

        $this->actingAs($this->agent)
            ->get(route('tickets.index', ['status' => 'open']))
            ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
    }

    public function test_index_search(): void
    {
        Ticket::create(['subject' => 'Login broken', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);
        Ticket::create(['subject' => 'Feature request', 'requester_name' => 'B', 'requester_email' => 'b@test.com']);

        $this->actingAs($this->agent)
            ->get(route('tickets.index', ['search' => 'Login']))
            ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
    }

    public function test_create_ticket(): void
    {
        $this->actingAs($this->agent)
            ->post(route('tickets.store'), [
                'subject' => 'New ticket',
                'body' => 'Ticket body',
                'requester_name' => 'Customer',
                'requester_email' => 'customer@example.com',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'New ticket',
            'priority' => 'high',
            'source' => 'web',
        ]);
    }

    public function test_create_ticket_with_labels(): void
    {
        $label = Label::create(['name' => 'Bug', 'slug' => 'bug']);

        $this->actingAs($this->agent)
            ->post(route('tickets.store'), [
                'subject' => 'Bug report',
                'requester_name' => 'Customer',
                'requester_email' => 'customer@example.com',
                'labels' => [$label->id],
            ]);

        $ticket = Ticket::where('subject', 'Bug report')->first();
        $this->assertTrue($ticket->labels->contains($label));
    }

    public function test_update_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->put(route('tickets.update', $ticket), [
                'status' => 'resolved',
                'priority' => 'urgent',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertEquals('urgent', $ticket->priority);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_show_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Tickets/Show'));
    }

    public function test_delete_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Delete me', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->delete(route('tickets.destroy', $ticket))
            ->assertRedirect(route('tickets.index'));

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    public function test_create_validation_requires_fields(): void
    {
        $this->actingAs($this->agent)
            ->post(route('tickets.store'), [])
            ->assertSessionHasErrors(['subject', 'requester_name', 'requester_email']);
    }
}
