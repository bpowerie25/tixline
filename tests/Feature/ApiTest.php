<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role_id' => Role::where('name', Role::ADMIN)->first()->id]);
    }

    public function test_api_requires_auth(): void
    {
        $this->getJson('/api/v1/tickets')->assertUnauthorized();
    }

    public function test_list_tickets(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_ticket_via_api(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/tickets', [
            'subject' => 'API ticket',
            'body' => 'Created via API',
            'requester_name' => 'API User',
            'requester_email' => 'api@example.com',
            'priority' => 'high',
        ])->assertCreated()
            ->assertJsonFragment(['subject' => 'API ticket', 'source' => 'api']);
    }

    public function test_show_ticket_via_api(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Test']);
    }

    public function test_update_ticket_via_api(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->putJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertOk()
            ->assertJsonFragment(['status' => 'resolved']);

        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_add_comment_via_api(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->postJson("/api/v1/tickets/{$ticket->id}/comments", [
            'body' => 'API comment',
            'is_internal' => true,
        ])->assertCreated()
            ->assertJsonFragment(['body' => 'API comment', 'type' => 'note']);
    }

    public function test_filter_tickets_by_status(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        Ticket::create(['subject' => 'Open', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'status' => 'open']);
        Ticket::create(['subject' => 'Closed', 'requester_name' => 'B', 'requester_email' => 'b@test.com', 'status' => 'closed']);

        $this->getJson('/api/v1/tickets?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_api_validation(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'requester_name', 'requester_email']);
    }
}
