<?php

namespace Tests\Unit;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_reference_on_create(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test ticket',
            'requester_name' => 'Test User',
            'requester_email' => 'test@example.com',
        ]);

        $this->assertMatchesRegularExpression('/^TKT-\d{6}$/', $ticket->fresh()->reference);
    }

    public function test_reference_uses_actual_id(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $expected = 'TKT-' . str_pad($ticket->id, 6, '0', STR_PAD_LEFT);
        $this->assertEquals($expected, $ticket->fresh()->reference);
    }

    public function test_sla_deadlines_set_when_policy_exists(): void
    {
        SlaPolicy::create([
            'name' => 'Normal SLA',
            'priority' => 'normal',
            'first_response_hours' => 4,
            'resolution_hours' => 24,
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'priority' => 'normal',
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->sla_response_due_at);
        $this->assertNotNull($ticket->sla_resolution_due_at);
    }

    public function test_sla_status_on_track(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'sla_resolution_due_at' => now()->addHours(10),
            'sla_response_due_at' => now()->addHours(4),
        ]);

        $this->assertEquals('on_track', $ticket->sla_status);
    }

    public function test_sla_status_breached(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'sla_resolution_due_at' => now()->subHour(),
        ]);

        $this->assertEquals('breached', $ticket->sla_status);
    }

    public function test_sla_status_met_for_resolved(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'status' => 'resolved',
            'sla_resolution_due_at' => now()->subHour(),
        ]);

        $this->assertEquals('met', $ticket->sla_status);
    }

    public function test_sla_status_null_without_policy(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $this->assertNull($ticket->sla_status);
    }

    public function test_default_status_is_open(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $this->assertEquals('open', $ticket->fresh()->status);
    }

    public function test_default_priority_is_normal(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $this->assertEquals('normal', $ticket->fresh()->priority);
    }

    public function test_custom_fields_cast_as_array(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'custom_fields' => ['category' => 'bug', 'severity' => 'high'],
        ]);

        $this->assertIsArray($ticket->fresh()->custom_fields);
        $this->assertEquals('bug', $ticket->fresh()->custom_fields['category']);
    }
}
