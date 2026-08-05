<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BodySanitizationExposureTest extends TestCase
{
    use RefreshDatabase;

    protected string $maliciousBody = '<p>Hello</p><script>alert("xss")</script>';

    public function test_agent_ticket_show_contains_sanitized_body_not_raw(): void
    {
        $agent = User::factory()->create(['role' => 'admin']);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'body' => $this->maliciousBody,
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $response = $this->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();

        // The Inertia page props must contain sanitized_body but NOT raw body
        $response->assertInertia(fn ($page) => $page
            ->has('ticket.sanitized_body')
            ->where('ticket.sanitized_body', fn ($val) => ! str_contains($val, 'alert("xss")'))
            ->missing('ticket.body')
        );
    }

    public function test_agent_ticket_index_does_not_expose_raw_body(): void
    {
        $agent = User::factory()->create(['role' => 'admin']);

        Ticket::create([
            'subject' => 'Test',
            'body' => $this->maliciousBody,
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $response = $this->actingAs($agent)
            ->get(route('tickets.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tickets.data.0')
            ->missing('tickets.data.0.body')
        );
    }

    public function test_portal_ticket_detail_does_not_expose_raw_body(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $ticket = Ticket::create([
            'subject' => 'Portal ticket',
            'body' => $this->maliciousBody,
            'requester_name' => 'Jane',
            'requester_email' => 'jane@example.com',
        ]);

        $ticket->comments()->create([
            'body' => '<div onclick="steal()">Comment</div>',
            'type' => 'reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('portal.ticket', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->missing('ticket.body')
            ->has('ticket.sanitized_body')
            ->has('ticket.comments.0.sanitized_body')
            ->missing('ticket.comments.0.body')
        );
    }

    public function test_comment_body_hidden_in_ticket_show(): void
    {
        $agent = User::factory()->create(['role' => 'admin']);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $ticket->comments()->create([
            'body' => '<img src=x onerror="document.location=\'evil\'">',
            'type' => 'reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('ticket.comments.0.sanitized_body')
            ->missing('ticket.comments.0.body')
        );
    }

    public function test_api_show_deliberately_exposes_raw_body(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $ticket = Ticket::create([
            'subject' => 'API Test',
            'body' => $this->maliciousBody,
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $response = $this->getJson("/api/v1/tickets/{$ticket->id}");

        $response->assertOk();
        // API deliberately includes raw body
        $response->assertJsonFragment(['body' => $this->maliciousBody]);
    }

    public function test_raw_body_preserved_in_database(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'body' => $this->maliciousBody,
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        // Direct DB access still has the raw body
        $this->assertEquals($this->maliciousBody, $ticket->body);
        $this->assertEquals($this->maliciousBody, $ticket->getAttributes()['body']);
    }
}
