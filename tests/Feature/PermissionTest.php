<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected Department $department;

    protected Team $teamA;

    protected Team $teamB;

    protected User $admin;

    protected User $groupManager;

    protected User $teamLead;

    protected User $agentA;

    protected User $agentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['name' => 'Support', 'slug' => 'support']);

        $this->teamA = Team::create(['name' => 'Tier 1', 'slug' => 'tier-1', 'department_id' => $this->department->id]);
        $this->teamB = Team::create(['name' => 'Tier 2', 'slug' => 'tier-2', 'department_id' => $this->department->id]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->groupManager = User::factory()->create(['role' => 'group_manager', 'team_id' => $this->teamA->id]);
        $this->teamLead = User::factory()->create(['role' => 'team_lead', 'team_id' => $this->teamA->id]);
        $this->agentA = User::factory()->create(['role' => 'agent', 'team_id' => $this->teamA->id]);
        $this->agentB = User::factory()->create(['role' => 'agent', 'team_id' => $this->teamB->id]);

        $this->department->update(['manager_id' => $this->groupManager->id]);
        $this->teamA->update(['lead_id' => $this->teamLead->id]);
    }

    // === Role hierarchy ===

    public function test_admin_is_at_least_all_roles(): void
    {
        $this->assertTrue($this->admin->isAtLeast('admin'));
        $this->assertTrue($this->admin->isAtLeast('group_manager'));
        $this->assertTrue($this->admin->isAtLeast('team_lead'));
        $this->assertTrue($this->admin->isAtLeast('agent'));
    }

    public function test_agent_is_not_team_lead(): void
    {
        $this->assertFalse($this->agentA->isAtLeast('team_lead'));
    }

    public function test_team_lead_is_at_least_agent(): void
    {
        $this->assertTrue($this->teamLead->isAtLeast('agent'));
    }

    // === Admin-only route protection ===

    public function test_agent_cannot_access_teams(): void
    {
        $this->actingAs($this->agentA)
            ->get(route('teams.index'))
            ->assertForbidden();
    }

    public function test_agent_cannot_access_workflows(): void
    {
        $this->actingAs($this->agentA)
            ->get(route('workflows.index'))
            ->assertForbidden();
    }

    public function test_agent_cannot_access_sla_policies(): void
    {
        $this->actingAs($this->agentA)
            ->get(route('sla-policies.index'))
            ->assertForbidden();
    }

    public function test_agent_cannot_access_tenants(): void
    {
        $this->actingAs($this->agentA)
            ->get(route('tenants.index'))
            ->assertForbidden();
    }

    public function test_agent_cannot_access_departments(): void
    {
        $this->actingAs($this->agentA)
            ->get(route('departments.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_teams(): void
    {
        $this->actingAs($this->admin)
            ->get(route('teams.index'))
            ->assertOk();
    }

    public function test_admin_can_access_departments(): void
    {
        $this->actingAs($this->admin)
            ->get(route('departments.index'))
            ->assertOk();
    }

    // === Reports access ===

    public function test_agent_cannot_access_reports(): void
    {
        $this->actingAs($this->agentA)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_team_lead_can_access_reports(): void
    {
        $this->actingAs($this->teamLead)
            ->get(route('reports.index'))
            ->assertOk();
    }

    // === Ticket visibility ===

    public function test_agent_sees_own_assigned_tickets(): void
    {
        Ticket::create(['subject' => 'Mine', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'assigned_to' => $this->agentA->id, 'team_id' => $this->teamA->id]);
        Ticket::create(['subject' => 'Other', 'requester_name' => 'B', 'requester_email' => 'b@test.com', 'assigned_to' => $this->agentB->id, 'team_id' => $this->teamB->id]);

        $visible = $this->agentA->visibleTicketsQuery()->get();

        // Agent sees: own ticket + team queue
        $this->assertTrue($visible->contains('subject', 'Mine'));
    }

    public function test_agent_sees_own_team_queue(): void
    {
        // Unassigned ticket in agent's team
        $ticket = Ticket::create(['subject' => 'Team queue', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'team_id' => $this->teamA->id]);

        $visible = $this->agentA->visibleTicketsQuery()->get();

        $this->assertTrue($visible->contains($ticket));
    }

    public function test_agent_does_not_see_other_team_tickets(): void
    {
        Ticket::create(['subject' => 'Other team', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'team_id' => $this->teamB->id, 'assigned_to' => $this->agentB->id]);

        $visible = $this->agentA->visibleTicketsQuery()->get();

        $this->assertFalse($visible->contains('subject', 'Other team'));
    }

    public function test_agent_sees_unassigned_tickets(): void
    {
        Ticket::create(['subject' => 'Unassigned', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $visible = $this->agentA->visibleTicketsQuery()->get();

        $this->assertTrue($visible->contains('subject', 'Unassigned'));
    }

    public function test_team_lead_sees_all_team_tickets(): void
    {
        Ticket::create(['subject' => 'Agent ticket', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'assigned_to' => $this->agentA->id, 'team_id' => $this->teamA->id]);

        $visible = $this->teamLead->visibleTicketsQuery()->get();

        $this->assertTrue($visible->contains('subject', 'Agent ticket'));
    }

    public function test_group_manager_sees_all_department_tickets(): void
    {
        Ticket::create(['subject' => 'Tier 1', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'team_id' => $this->teamA->id]);
        Ticket::create(['subject' => 'Tier 2', 'requester_name' => 'B', 'requester_email' => 'b@test.com', 'team_id' => $this->teamB->id]);

        $visible = $this->groupManager->visibleTicketsQuery()->get();

        $this->assertTrue($visible->contains('subject', 'Tier 1'));
        $this->assertTrue($visible->contains('subject', 'Tier 2'));
    }

    public function test_admin_sees_all_tickets(): void
    {
        Ticket::create(['subject' => 'Any', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'team_id' => $this->teamB->id]);

        $visible = $this->admin->visibleTicketsQuery()->get();

        $this->assertTrue($visible->contains('subject', 'Any'));
    }

    // === Ticket policy — update/delete ===

    public function test_agent_can_update_own_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Mine', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'assigned_to' => $this->agentA->id]);

        $this->actingAs($this->agentA)
            ->put(route('tickets.update', $ticket), ['status' => 'pending'])
            ->assertRedirect();
    }

    public function test_agent_cannot_update_others_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Other', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'assigned_to' => $this->agentB->id, 'team_id' => $this->teamB->id]);

        $this->actingAs($this->agentA)
            ->put(route('tickets.update', $ticket), ['status' => 'pending'])
            ->assertForbidden();
    }

    public function test_team_lead_can_update_team_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Team', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'assigned_to' => $this->agentA->id, 'team_id' => $this->teamA->id]);

        $this->actingAs($this->teamLead)
            ->put(route('tickets.update', $ticket), ['status' => 'resolved'])
            ->assertRedirect();
    }

    public function test_agent_cannot_delete_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'No delete', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'assigned_to' => $this->agentA->id]);

        $this->actingAs($this->agentA)
            ->delete(route('tickets.destroy', $ticket))
            ->assertForbidden();
    }

    public function test_team_lead_can_delete_ticket(): void
    {
        $ticket = Ticket::create(['subject' => 'Delete me', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'team_id' => $this->teamA->id]);

        $this->actingAs($this->teamLead)
            ->delete(route('tickets.destroy', $ticket))
            ->assertRedirect();

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
    }

    // === Department CRUD ===

    public function test_create_department(): void
    {
        $this->actingAs($this->admin)
            ->post(route('departments.store'), [
                'name' => 'Engineering',
                'manager_id' => $this->groupManager->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['name' => 'Engineering', 'slug' => 'engineering']);
    }

    public function test_team_belongs_to_department(): void
    {
        $this->assertEquals($this->department->id, $this->teamA->department_id);
        $this->assertEquals(2, $this->department->teams()->count());
    }

    public function test_department_has_manager(): void
    {
        $this->assertEquals($this->groupManager->id, $this->department->manager_id);
    }
}
