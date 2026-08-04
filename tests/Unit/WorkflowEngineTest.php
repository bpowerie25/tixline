<?php

namespace Tests\Unit;

use App\Models\Label;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new WorkflowEngine();
    }

    public function test_workflow_assigns_to_team(): void
    {
        $team = Team::create(['name' => 'Billing', 'slug' => 'billing']);

        Workflow::create([
            'name' => 'Billing routing',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'any', 'rules' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'invoice'],
            ]],
            'actions' => [
                ['type' => 'assign_to_team', 'value' => $team->id],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Invoice query',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $this->engine->run($ticket, 'ticket_created');

        $this->assertEquals($team->id, $ticket->fresh()->team_id);
    }

    public function test_workflow_sets_priority(): void
    {
        Workflow::create([
            'name' => 'Urgent escalation',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'any', 'rules' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'critical'],
            ]],
            'actions' => [
                ['type' => 'set_priority', 'value' => 'urgent'],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'System critical failure',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'priority' => 'normal',
        ]);

        $this->engine->run($ticket, 'ticket_created');

        $this->assertEquals('urgent', $ticket->fresh()->priority);
    }

    public function test_workflow_adds_label(): void
    {
        $label = Label::create(['name' => 'Bug', 'slug' => 'bug']);

        Workflow::create([
            'name' => 'Auto-label bugs',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'any', 'rules' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'error'],
            ]],
            'actions' => [
                ['type' => 'add_label', 'value' => $label->id],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Getting error on login',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $this->engine->run($ticket, 'ticket_created');

        $this->assertTrue($ticket->fresh()->labels->contains($label));
    }

    public function test_inactive_workflow_is_skipped(): void
    {
        Workflow::create([
            'name' => 'Disabled rule',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [
                ['type' => 'set_priority', 'value' => 'urgent'],
            ],
            'is_active' => false,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'priority' => 'low',
        ]);

        $this->engine->run($ticket, 'ticket_created');

        $this->assertEquals('low', $ticket->fresh()->priority);
    }

    public function test_workflow_condition_match_all(): void
    {
        Workflow::create([
            'name' => 'Match all',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'subject', 'operator' => 'contains', 'value' => 'billing'],
                ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
            ]],
            'actions' => [
                ['type' => 'set_status', 'value' => 'pending'],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        // Only matches one condition — should not trigger
        $ticket = Ticket::create([
            'subject' => 'Billing issue',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'priority' => 'normal',
        ]);

        $this->engine->run($ticket, 'ticket_created');
        $this->assertEquals('open', $ticket->fresh()->status);

        // Matches both — should trigger
        $ticket2 = Ticket::create([
            'subject' => 'Billing issue',
            'requester_name' => 'Test',
            'requester_email' => 'test2@example.com',
            'priority' => 'high',
        ]);

        $this->engine->run($ticket2, 'ticket_created');
        $this->assertEquals('pending', $ticket2->fresh()->status);
    }

    public function test_round_robin_distributes_across_team(): void
    {
        $team = Team::create(['name' => 'Support', 'slug' => 'support']);
        $agent1 = User::factory()->create(['team_id' => $team->id]);
        $agent2 = User::factory()->create(['team_id' => $team->id]);

        Workflow::create([
            'name' => 'Round robin',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [
                ['type' => 'assign_to_team', 'value' => $team->id],
                ['type' => 'round_robin', 'value' => $team->id],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $t1 = Ticket::create(['subject' => 'T1', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);
        $this->engine->run($t1, 'ticket_created');

        $t2 = Ticket::create(['subject' => 'T2', 'requester_name' => 'B', 'requester_email' => 'b@test.com']);
        $this->engine->run($t2, 'ticket_created');

        $assignees = [$t1->fresh()->assigned_to, $t2->fresh()->assigned_to];

        $this->assertContains($agent1->id, $assignees);
        $this->assertContains($agent2->id, $assignees);
        $this->assertNotEquals($assignees[0], $assignees[1]);
    }

    public function test_workflow_wrong_trigger_is_skipped(): void
    {
        Workflow::create([
            'name' => 'On update only',
            'trigger_event' => 'ticket_updated',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [
                ['type' => 'set_priority', 'value' => 'urgent'],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
            'priority' => 'low',
        ]);

        $this->engine->run($ticket, 'ticket_created');
        $this->assertEquals('low', $ticket->fresh()->priority);
    }
}
