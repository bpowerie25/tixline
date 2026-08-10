<?php

namespace Tests\Feature;

use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamLabelWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);
    }

    // Teams
    public function test_create_team(): void
    {
        $this->actingAs($this->admin)
            ->post(route('teams.store'), ['name' => 'Engineering', 'color' => '#10b981'])
            ->assertRedirect();

        $this->assertDatabaseHas('teams', ['name' => 'Engineering', 'slug' => 'engineering']);
    }

    public function test_update_team(): void
    {
        $team = Team::create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->actingAs($this->admin)
            ->put(route('teams.update', $team), ['name' => 'New Name'])
            ->assertRedirect();

        $this->assertEquals('New Name', $team->fresh()->name);
    }

    public function test_delete_team(): void
    {
        $team = Team::create(['name' => 'Delete Me', 'slug' => 'delete-me']);

        $this->actingAs($this->admin)
            ->delete(route('teams.destroy', $team))
            ->assertRedirect();

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    // Labels
    public function test_create_label(): void
    {
        $this->actingAs($this->admin)
            ->post(route('labels.store'), ['name' => 'Feature', 'color' => '#8b5cf6'])
            ->assertRedirect();

        $this->assertDatabaseHas('labels', ['name' => 'Feature', 'slug' => 'feature']);
    }

    public function test_update_label(): void
    {
        $label = Label::create(['name' => 'Old', 'slug' => 'old']);

        $this->actingAs($this->admin)
            ->put(route('labels.update', $label), ['name' => 'Updated', 'color' => '#000000'])
            ->assertRedirect();

        $this->assertEquals('Updated', $label->fresh()->name);
    }

    public function test_delete_label(): void
    {
        $label = Label::create(['name' => 'Delete', 'slug' => 'delete']);

        $this->actingAs($this->admin)
            ->delete(route('labels.destroy', $label))
            ->assertRedirect();

        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
    }

    // Workflows
    public function test_create_workflow(): void
    {
        $this->actingAs($this->admin)
            ->post(route('workflows.store'), [
                'name' => 'Auto-label',
                'trigger_event' => 'ticket_created',
                'conditions' => ['match' => 'all', 'rules' => []],
                'actions' => [['type' => 'set_priority', 'value' => 'high']],
                'is_active' => true,
                'priority' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workflows', ['name' => 'Auto-label']);
    }

    public function test_update_workflow(): void
    {
        $workflow = Workflow::create([
            'name' => 'Old',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [],
            'is_active' => true,
            'priority' => 0,
        ]);

        $this->actingAs($this->admin)
            ->put(route('workflows.update', $workflow), [
                'name' => 'Updated',
                'trigger_event' => 'ticket_updated',
                'conditions' => ['match' => 'all', 'rules' => []],
                'actions' => [['type' => 'set_priority', 'value' => 'low']],
            ])
            ->assertRedirect();

        $this->assertEquals('Updated', $workflow->fresh()->name);
    }

    public function test_delete_workflow(): void
    {
        $workflow = Workflow::create([
            'name' => 'Delete',
            'trigger_event' => 'ticket_created',
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [],
            'is_active' => true,
            'priority' => 0,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('workflows.destroy', $workflow))
            ->assertRedirect();

        $this->assertDatabaseMissing('workflows', ['id' => $workflow->id]);
    }
}
