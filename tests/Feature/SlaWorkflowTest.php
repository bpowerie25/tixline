<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(WorkflowEngine::class);
    }

    public function test_sla_response_breached_triggers_workflow(): void
    {
        $escalationTeam = Team::create(['name' => 'Escalation', 'slug' => 'escalation']);

        Workflow::create([
            'name' => 'Escalate on SLA response breach',
            'trigger_event' => 'sla_response_breached',
            'events' => [['entity' => 'ticket', 'action' => 'sla_response_breached']],
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [
                ['type' => 'assign_to_team', 'value' => $escalationTeam->id],
                ['type' => 'set_priority', 'value' => 'urgent'],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Waiting too long',
            'requester_name' => 'Customer',
            'requester_email' => 'c@test.com',
            'priority' => 'normal',
        ]);

        $this->engine->run($ticket, 'sla_response_breached');

        $ticket->refresh();
        $this->assertEquals($escalationTeam->id, $ticket->team_id);
        $this->assertEquals('urgent', $ticket->priority);
    }

    public function test_sla_warning_triggers_workflow(): void
    {
        $lead = User::factory()->create(['role_id' => Role::where('name', Role::TEAM_LEAD)->first()->id]);

        Workflow::create([
            'name' => 'Alert lead on SLA warning',
            'trigger_event' => 'sla_warning',
            'events' => [['entity' => 'ticket', 'action' => 'sla_warning']],
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [
                ['type' => 'add_note', 'value' => 'SLA at risk - needs attention'],
            ],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'SLA approaching',
            'requester_name' => 'Customer',
            'requester_email' => 'c@test.com',
        ]);

        $this->engine->run($ticket, 'sla_warning');

        $this->assertEquals(1, $ticket->comments()->count());
        $this->assertEquals('SLA at risk - needs attention', $ticket->comments()->first()->body);
    }

    public function test_status_changed_event_fires(): void
    {
        Workflow::create([
            'name' => 'On resolve, add note',
            'trigger_event' => 'ticket_status_changed',
            'events' => [['entity' => 'ticket', 'action' => 'ticket_status_changed']],
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'resolved'],
            ]],
            'actions' => [['type' => 'add_note', 'value' => 'Ticket resolved']],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'A',
            'requester_email' => 'a@t.com',
            'status' => 'resolved',
        ]);

        $this->engine->run($ticket, 'ticket_status_changed');

        $this->assertEquals(1, $ticket->comments()->where('body', 'Ticket resolved')->count());
    }

    public function test_status_changed_with_condition_on_new_value(): void
    {
        Workflow::create([
            'name' => 'Only on pending',
            'trigger_event' => 'ticket_status_changed',
            'events' => [['entity' => 'ticket', 'action' => 'ticket_status_changed']],
            'conditions' => ['match' => 'all', 'rules' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'pending'],
            ]],
            'actions' => [['type' => 'add_note', 'value' => 'Now pending']],
            'is_active' => true,
            'priority' => 0,
        ]);

        // Ticket is resolved — condition should NOT match
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'A',
            'requester_email' => 'a@t.com',
            'status' => 'resolved',
        ]);

        $this->engine->run($ticket, 'ticket_status_changed');

        $this->assertEquals(0, $ticket->comments()->count());
    }

    public function test_field_change_event_fires_through_controller(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('name', Role::ADMIN)->first()->id]);

        Workflow::create([
            'name' => 'On assign',
            'trigger_event' => 'ticket_assigned',
            'events' => [['entity' => 'ticket', 'action' => 'ticket_assigned']],
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [['type' => 'add_note', 'value' => 'Agent assigned']],
            'is_active' => true,
            'priority' => 0,
        ]);

        $ticket = Ticket::create([
            'subject' => 'Assign me',
            'requester_name' => 'A',
            'requester_email' => 'a@t.com',
        ]);

        $this->actingAs($admin)
            ->put(route('tickets.update', $ticket), [
                'assigned_to' => $admin->id,
            ]);

        // Should have the automated note
        $this->assertTrue($ticket->comments()->where('body', 'Agent assigned')->exists());
    }
}
