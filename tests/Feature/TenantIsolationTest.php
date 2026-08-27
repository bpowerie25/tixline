<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $agentA;

    protected User $agentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $this->tenantB = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        $this->agentA = User::factory()->create(['role_id' => Role::where('name', Role::AGENT)->first()->id, 'tenant_id' => $this->tenantA->id]);
        $this->agentB = User::factory()->create(['role_id' => Role::where('name', Role::AGENT)->first()->id, 'tenant_id' => $this->tenantB->id]);
    }

    protected function setTenant(Tenant $tenant): void
    {
        app()->instance('tenant', $tenant);
    }

    public function test_agent_only_sees_own_tenant_tickets(): void
    {
        $this->setTenant($this->tenantA);
        Ticket::create(['subject' => 'Acme ticket', 'requester_name' => 'A', 'requester_email' => 'a@acme.com', 'tenant_id' => $this->tenantA->id]);

        $this->setTenant($this->tenantB);
        Ticket::create(['subject' => 'Globex ticket', 'requester_name' => 'B', 'requester_email' => 'b@globex.com', 'tenant_id' => $this->tenantB->id]);

        // Agent A should only see Acme tickets
        $this->setTenant($this->tenantA);
        $this->actingAs($this->agentA)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('tickets.data', 1));
    }

    public function test_agent_cannot_view_other_tenant_ticket(): void
    {
        $this->setTenant($this->tenantB);
        $ticket = Ticket::create(['subject' => 'Globex', 'requester_name' => 'B', 'requester_email' => 'b@globex.com', 'tenant_id' => $this->tenantB->id]);

        // Agent A tries to view Globex ticket
        $this->setTenant($this->tenantA);
        $this->actingAs($this->agentA)
            ->get(route('tickets.show', $ticket->id))
            ->assertNotFound();
    }

    public function test_api_scoped_to_tenant(): void
    {
        $this->setTenant($this->tenantA);
        Ticket::create(['subject' => 'Acme API', 'requester_name' => 'A', 'requester_email' => 'a@acme.com', 'tenant_id' => $this->tenantA->id]);

        $this->setTenant($this->tenantB);
        Ticket::create(['subject' => 'Globex API', 'requester_name' => 'B', 'requester_email' => 'b@globex.com', 'tenant_id' => $this->tenantB->id]);

        $this->setTenant($this->tenantA);
        Sanctum::actingAs($this->agentA, ['*']);
        $response = $this->getJson('/api/v1/tickets');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_customer_cannot_see_other_customers_tickets(): void
    {
        $customerA = Customer::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('pass')]);
        $customerB = Customer::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => bcrypt('pass')]);

        Ticket::create(['subject' => 'Alice ticket', 'requester_name' => 'Alice', 'requester_email' => 'alice@example.com']);
        $bobTicket = Ticket::create(['subject' => 'Bob ticket', 'requester_name' => 'Bob', 'requester_email' => 'bob@example.com']);

        // Alice tries to view Bob's ticket
        $this->actingAs($customerA, 'customer')
            ->get(route('portal.ticket', $bobTicket->id))
            ->assertNotFound();
    }

    public function test_customer_cannot_reply_to_other_customers_ticket(): void
    {
        $customerA = Customer::create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => bcrypt('pass')]);
        $bobTicket = Ticket::create(['subject' => 'Bob ticket', 'requester_name' => 'Bob', 'requester_email' => 'bob@example.com']);

        $this->actingAs($customerA, 'customer')
            ->post(route('portal.ticket.reply', $bobTicket->id), ['body' => 'Hacked reply'])
            ->assertNotFound();

        $this->assertEquals(0, $bobTicket->comments()->count());
    }

    public function test_ticket_gets_tenant_id_on_creation(): void
    {
        $this->setTenant($this->tenantA);

        $ticket = Ticket::create([
            'subject' => 'Auto-scoped',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $this->assertEquals($this->tenantA->id, $ticket->fresh()->tenant_id);
    }
}
