<?php

namespace Tests\Unit;

use App\Models\Automation;
use App\Models\Team;
use App\Models\Ticket;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected AutomationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(AutomationEngine::class);
    }

    public function test_fires_on_hours_since_created(): void
    {
        $team = Team::create(['name' => 'Escalation', 'slug' => 'escalation']);

        Automation::create([
            'name' => 'Escalate stale tickets',
            'time_conditions' => [
                ['field' => 'hours_since_created', 'operator' => 'greater_than', 'value' => 4],
            ],
            'actions' => [['type' => 'assign_to_team', 'value' => $team->id]],
            'is_active' => true,
        ]);

        // Ticket created 5 hours ago — should match
        $old = Ticket::create(['subject' => 'Old', 'requester_name' => 'A', 'requester_email' => 'a@t.com']);
        $old->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();

        // Ticket created 1 hour ago — should NOT match
        $fresh = Ticket::create(['subject' => 'Fresh', 'requester_name' => 'B', 'requester_email' => 'b@t.com']);
        $fresh->forceFill(['created_at' => now()->subHour()])->saveQuietly();

        $results = $this->engine->runAll();

        $this->assertCount(1, $results);
        $this->assertEquals($team->id, $old->fresh()->team_id);
        $this->assertNull($fresh->fresh()->team_id);
    }

    public function test_fires_on_hours_without_response(): void
    {
        Automation::create([
            'name' => 'No response alert',
            'time_conditions' => [
                ['field' => 'hours_without_response', 'operator' => 'greater_than', 'value' => 2],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'high']],
            'is_active' => true,
        ]);

        // No response, created 3 hours ago
        $ticket = Ticket::create(['subject' => 'Waiting', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'priority' => 'normal']);
        $ticket->forceFill(['created_at' => now()->subHours(3)])->saveQuietly();

        $this->engine->runAll();

        $this->assertEquals('high', $ticket->fresh()->priority);
    }

    public function test_does_not_fire_on_responded_tickets(): void
    {
        Automation::create([
            'name' => 'No response alert',
            'time_conditions' => [
                ['field' => 'hours_without_response', 'operator' => 'greater_than', 'value' => 2],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'high']],
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Responded',
            'requester_name' => 'A',
            'requester_email' => 'a@t.com',
            'priority' => 'normal',
            'first_responded_at' => now()->subHour(),
        ]);
        $ticket->forceFill(['created_at' => now()->subHours(3)])->saveQuietly();

        $this->engine->runAll();

        // hours_without_response returns null for responded tickets
        $this->assertEquals('normal', $ticket->fresh()->priority);
    }

    public function test_run_once_per_ticket(): void
    {
        Automation::create([
            'name' => 'Run once',
            'time_conditions' => [
                ['field' => 'hours_since_created', 'operator' => 'greater_than', 'value' => 1],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
            'is_active' => true,
            'run_once_per_ticket' => true,
        ]);

        $ticket = Ticket::create(['subject' => 'Once', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'priority' => 'low']);
        $ticket->forceFill(['created_at' => now()->subHours(2)])->saveQuietly();

        // First run — should fire
        $results = $this->engine->runAll();
        $this->assertCount(1, $results);
        $this->assertEquals('urgent', $ticket->fresh()->priority);

        // Reset priority to test second run
        Ticket::withoutGlobalScopes()->where('id', $ticket->id)->update(['priority' => 'low']);

        // Second run — should NOT fire (already recorded)
        $results = $this->engine->runAll();
        $this->assertCount(0, $results);
        $this->assertEquals('low', $ticket->fresh()->priority);
    }

    public function test_skips_resolved_tickets(): void
    {
        Automation::create([
            'name' => 'Stale check',
            'time_conditions' => [
                ['field' => 'hours_since_created', 'operator' => 'greater_than', 'value' => 1],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
            'is_active' => true,
        ]);

        $ticket = Ticket::create(['subject' => 'Resolved', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'status' => 'resolved', 'priority' => 'low']);
        $ticket->forceFill(['created_at' => now()->subHours(5)])->saveQuietly();

        $results = $this->engine->runAll();
        $this->assertCount(0, $results);
    }

    public function test_combines_time_and_ticket_conditions(): void
    {
        Automation::create([
            'name' => 'Stale high priority',
            'time_conditions' => [
                ['field' => 'hours_since_created', 'operator' => 'greater_than', 'value' => 2],
            ],
            'ticket_conditions' => [
                'match' => 'all',
                'rules' => [
                    ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                ],
            ],
            'actions' => [['type' => 'set_status', 'value' => 'pending']],
            'is_active' => true,
        ]);

        // High + old = match
        $t1 = Ticket::create(['subject' => 'High old', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'priority' => 'high']);
        $t1->forceFill(['created_at' => now()->subHours(3)])->saveQuietly();

        // Normal + old = no match (ticket condition fails)
        $t2 = Ticket::create(['subject' => 'Normal old', 'requester_name' => 'B', 'requester_email' => 'b@t.com', 'priority' => 'normal']);
        $t2->forceFill(['created_at' => now()->subHours(3)])->saveQuietly();

        $results = $this->engine->runAll();

        $this->assertCount(1, $results);
        $this->assertEquals('pending', $t1->fresh()->status);
        $this->assertEquals('open', $t2->fresh()->status);
    }

    public function test_hours_until_sla_resolution(): void
    {
        Automation::create([
            'name' => 'SLA approaching',
            'time_conditions' => [
                ['field' => 'hours_until_sla_resolution', 'operator' => 'less_than', 'value' => 2],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
            'is_active' => true,
        ]);

        // SLA due in 1 hour — should match
        $t1 = Ticket::create([
            'subject' => 'Close SLA',
            'requester_name' => 'A',
            'requester_email' => 'a@t.com',
            'priority' => 'normal',
            'sla_resolution_due_at' => now()->addHour(),
        ]);

        // SLA due in 10 hours — should NOT match
        $t2 = Ticket::create([
            'subject' => 'Far SLA',
            'requester_name' => 'B',
            'requester_email' => 'b@t.com',
            'priority' => 'normal',
            'sla_resolution_due_at' => now()->addHours(10),
        ]);

        $this->engine->runAll();

        $this->assertEquals('urgent', $t1->fresh()->priority);
        $this->assertEquals('normal', $t2->fresh()->priority);
    }

    public function test_inactive_automation_skipped(): void
    {
        Automation::create([
            'name' => 'Disabled',
            'time_conditions' => [
                ['field' => 'hours_since_created', 'operator' => 'greater_than', 'value' => 0],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
            'is_active' => false,
        ]);

        Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'priority' => 'low']);

        $results = $this->engine->runAll();
        $this->assertCount(0, $results);
    }
}
