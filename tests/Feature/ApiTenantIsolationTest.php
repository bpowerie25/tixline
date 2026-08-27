<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The API used to run with no tenant bound: ResolveTenant is only registered
 * on the `web` middleware group, and TenantScope no-ops when nothing is bound.
 * These tests drive real HTTP requests with a real bearer token and never bind
 * a tenant by hand, which is what the existing isolation test did.
 */
class ApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $acme;

    protected Tenant $globex;

    protected User $acmeAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'is_active' => true]);
        $this->globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex', 'is_active' => true]);

        $this->acmeAgent = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
            'tenant_id' => $this->acme->id,
        ]);

        Ticket::create(['subject' => 'Acme ticket', 'requester_name' => 'A', 'requester_email' => 'a@acme.com', 'tenant_id' => $this->acme->id]);
        Ticket::create(['subject' => 'Globex ticket', 'requester_name' => 'B', 'requester_email' => 'b@globex.com', 'tenant_id' => $this->globex->id]);
    }

    protected function tokenFor(User $user): string
    {
        $token = $user->createToken('Integration', ['tickets:read', 'tickets:write']);
        $token->accessToken->forceFill(['tenant_id' => $user->tenant_id])->save();

        return $token->plainTextToken;
    }

    public function test_a_key_only_lists_its_own_tenants_tickets(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->acmeAgent))
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['subject' => 'Acme ticket'])
            ->assertJsonMissing(['subject' => 'Globex ticket']);
    }

    public function test_a_key_cannot_read_another_tenants_ticket_by_id(): void
    {
        $globexTicket = Ticket::withoutGlobalScopes()->where('tenant_id', $this->globex->id)->first();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->acmeAgent))
            ->getJson("/api/v1/tickets/{$globexTicket->id}")
            ->assertNotFound();
    }

    public function test_a_key_cannot_update_another_tenants_ticket(): void
    {
        $globexTicket = Ticket::withoutGlobalScopes()->where('tenant_id', $this->globex->id)->first();

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->acmeAgent))
            ->putJson("/api/v1/tickets/{$globexTicket->id}", ['status' => 'closed'])
            ->assertNotFound();

        $this->assertEquals('open', $globexTicket->fresh()->status);
    }

    public function test_a_ticket_created_through_the_api_belongs_to_the_keys_tenant(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->acmeAgent))
            ->postJson('/api/v1/tickets', [
                'subject' => 'Via API',
                'requester_name' => 'A',
                'requester_email' => 'a@acme.com',
            ])
            ->assertCreated();

        $ticket = Ticket::withoutGlobalScopes()->where('subject', 'Via API')->first();

        $this->assertEquals($this->acme->id, $ticket->tenant_id);
    }

    public function test_a_key_presented_on_another_tenants_domain_is_rejected(): void
    {
        $this->globex->update(['domain' => 'support.globex.test']);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->acmeAgent))
            ->getJson('http://support.globex.test/api/v1/tickets')
            ->assertForbidden();
    }

    public function test_a_key_belonging_to_a_deactivated_tenant_is_rejected(): void
    {
        $this->acme->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($this->acmeAgent))
            ->getJson('/api/v1/tickets')
            ->assertForbidden();
    }
}
