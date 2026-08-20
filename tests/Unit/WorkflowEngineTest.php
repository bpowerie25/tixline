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
        $this->engine = new WorkflowEngine;
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
        $agent1 = User::factory()->create();
        $agent1->teams()->attach($team);
        $agent2 = User::factory()->create();
        $agent2->teams()->attach($team);

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

    public function test_nested_condition_groups(): void
    {
        // Rule: subject contains "billing" AND (priority is high OR priority is urgent)
        Workflow::create([
            'name' => 'Nested test',
            'trigger_event' => 'ticket_created',
            'conditions' => [
                'match' => 'all',
                'rules' => [
                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'billing'],
                    [
                        'match' => 'any',
                        'rules' => [
                            ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                            ['field' => 'priority', 'operator' => 'equals', 'value' => 'urgent'],
                        ],
                    ],
                ],
            ],
            'actions' => [['type' => 'set_status', 'value' => 'pending']],
            'is_active' => true,
            'priority' => 0,
        ]);

        // billing + normal = should NOT match (nested OR fails)
        $t1 = Ticket::create(['subject' => 'Billing issue', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'priority' => 'normal']);
        $this->engine->run($t1, 'ticket_created');
        $this->assertEquals('open', $t1->fresh()->status);

        // billing + high = should match
        $t2 = Ticket::create(['subject' => 'Billing urgent', 'requester_name' => 'B', 'requester_email' => 'b@test.com', 'priority' => 'high']);
        $this->engine->run($t2, 'ticket_created');
        $this->assertEquals('pending', $t2->fresh()->status);

        // non-billing + urgent = should NOT match (top-level AND fails)
        $t3 = Ticket::create(['subject' => 'General question', 'requester_name' => 'C', 'requester_email' => 'c@test.com', 'priority' => 'urgent']);
        $this->engine->run($t3, 'ticket_created');
        $this->assertEquals('open', $t3->fresh()->status);
    }

    public function test_deeply_nested_groups(): void
    {
        // (A AND (B OR (C AND D)))
        Workflow::create([
            'name' => 'Deep nesting',
            'trigger_event' => 'ticket_created',
            'conditions' => [
                'match' => 'all',
                'rules' => [
                    ['field' => 'source', 'operator' => 'equals', 'value' => 'email'],
                    [
                        'match' => 'any',
                        'rules' => [
                            ['field' => 'priority', 'operator' => 'equals', 'value' => 'urgent'],
                            [
                                'match' => 'all',
                                'rules' => [
                                    ['field' => 'subject', 'operator' => 'contains', 'value' => 'server'],
                                    ['field' => 'priority', 'operator' => 'equals', 'value' => 'high'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'actions' => [['type' => 'set_status', 'value' => 'pending']],
            'is_active' => true,
            'priority' => 0,
        ]);

        // email + high + "server" = match via (C AND D) path
        $t1 = Ticket::create(['subject' => 'Server down', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'priority' => 'high', 'source' => 'email']);
        $this->engine->run($t1, 'ticket_created');
        $this->assertEquals('pending', $t1->fresh()->status);

        // email + urgent = match via B path
        $t2 = Ticket::create(['subject' => 'Anything', 'requester_name' => 'B', 'requester_email' => 'b@t.com', 'priority' => 'urgent', 'source' => 'email']);
        $this->engine->run($t2, 'ticket_created');
        $this->assertEquals('pending', $t2->fresh()->status);

        // web + urgent = no match (source fails)
        $t3 = Ticket::create(['subject' => 'Web', 'requester_name' => 'C', 'requester_email' => 'c@t.com', 'priority' => 'urgent', 'source' => 'web']);
        $this->engine->run($t3, 'ticket_created');
        $this->assertEquals('open', $t3->fresh()->status);
    }

    public function test_multiple_events(): void
    {
        Workflow::create([
            'name' => 'Multi-event',
            'trigger_event' => 'ticket_created',
            'events' => [
                ['entity' => 'ticket', 'action' => 'ticket_created'],
                ['entity' => 'ticket', 'action' => 'ticket_updated'],
            ],
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [['type' => 'set_priority', 'value' => 'high']],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'priority' => 'low']);

        // Should trigger on ticket_updated too
        $this->engine->run($ticket, 'ticket_updated');
        $this->assertEquals('high', $ticket->fresh()->priority);
    }

    public function test_add_note_action(): void
    {
        Workflow::create([
            'name' => 'Auto-note',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [['type' => 'add_note', 'value' => 'Ticket received by automation']],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@t.com']);
        $this->engine->run($ticket, 'ticket_created');

        $this->assertEquals(1, $ticket->comments()->count());
        $comment = $ticket->comments()->first();
        $this->assertEquals('note', $comment->type);
        $this->assertTrue($comment->is_internal);
    }

    public function test_remove_label_action(): void
    {
        $label = Label::create(['name' => 'Remove Me', 'slug' => 'remove-me']);

        Workflow::create([
            'name' => 'Remove label',
            'trigger_event' => 'ticket_updated',
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'resolved'],
            ]],
            'actions' => [['type' => 'remove_label', 'value' => $label->id]],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@t.com', 'status' => 'resolved']);
        $ticket->labels()->attach($label);

        $this->engine->run($ticket, 'ticket_updated');
        $this->assertFalse($ticket->fresh()->labels->contains($label));
    }
}
