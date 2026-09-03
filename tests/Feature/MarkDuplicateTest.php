<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create([
            'role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id,
        ]);
    }

    public function test_can_mark_ticket_as_duplicate(): void
    {
        $original = Ticket::create(['subject' => 'Original', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);
        $duplicate = Ticket::create(['subject' => 'Same issue', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->post(route('tickets.mark-duplicate', $duplicate), [
                'duplicate_of' => $original->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $duplicate->refresh();
        $this->assertEquals($original->id, $duplicate->duplicate_of);
        $this->assertEquals('closed', $duplicate->status);
        $this->assertNotNull($duplicate->resolved_at);
    }

    public function test_cannot_mark_ticket_as_duplicate_of_itself(): void
    {
        $ticket = Ticket::create(['subject' => 'Test', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->post(route('tickets.mark-duplicate', $ticket), [
                'duplicate_of' => $ticket->id,
            ])
            ->assertSessionHasErrors('duplicate_of');
    }

    public function test_cannot_mark_as_duplicate_of_another_duplicate(): void
    {
        $original = Ticket::create(['subject' => 'Original', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);
        $dup1 = Ticket::create(['subject' => 'Dup 1', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'duplicate_of' => $original->id, 'status' => 'closed']);
        $dup2 = Ticket::create(['subject' => 'Dup 2', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->post(route('tickets.mark-duplicate', $dup2), [
                'duplicate_of' => $dup1->id,
            ])
            ->assertSessionHasErrors('duplicate_of');
    }

    public function test_duplicate_creates_system_comment(): void
    {
        $original = Ticket::create(['subject' => 'Original', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);
        $duplicate = Ticket::create(['subject' => 'Dup', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->actingAs($this->agent)
            ->post(route('tickets.mark-duplicate', $duplicate), [
                'duplicate_of' => $original->id,
            ]);

        $this->assertEquals(1, $duplicate->comments()->where('type', 'system')->count());
    }

    public function test_original_ticket_lists_its_duplicates(): void
    {
        $original = Ticket::create(['subject' => 'Original', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);
        $dup = Ticket::create(['subject' => 'Dup', 'requester_name' => 'A', 'requester_email' => 'a@test.com', 'duplicate_of' => $original->id, 'status' => 'closed']);

        $this->assertEquals(1, $original->duplicates()->count());
        $this->assertEquals($dup->id, $original->duplicates()->first()->id);
    }
}
