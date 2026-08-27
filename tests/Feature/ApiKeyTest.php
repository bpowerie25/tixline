<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);
    }

    protected function keyFor(User $user, array $abilities = ['tickets:read', 'tickets:write'], $expiresAt = null): string
    {
        $token = $user->createToken('Test key', $abilities, $expiresAt);
        $token->accessToken->forceFill(['tenant_id' => $user->tenant_id])->save();

        return $token->plainTextToken;
    }

    public function test_an_agent_can_create_a_key_and_see_the_secret_once(): void
    {
        $this->actingAs($this->admin)
            ->post(route('api-keys.store'), [
                'name' => 'Zapier',
                'abilities' => ['tickets:read'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Zapier']);

        // The plaintext is flashed for exactly one render
        $this->actingAs($this->admin)
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('plaintextKey')
                ->where('keys.0.name', 'Zapier')
                ->where('keys.0.abilities', ['tickets:read']));

        $this->actingAs($this->admin)
            ->get(route('api-keys.index'))
            ->assertInertia(fn ($page) => $page->where('plaintextKey', null));
    }

    public function test_unknown_abilities_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('api-keys.store'), [
                'name' => 'Overreaching',
                'abilities' => ['*'],
            ])
            ->assertSessionHasErrors('abilities.0');
    }

    public function test_a_key_can_be_revoked(): void
    {
        $this->keyFor($this->admin);
        $key = ApiKey::first();

        $this->actingAs($this->admin)
            ->delete(route('api-keys.destroy', $key->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $key->id]);
    }

    public function test_another_tenants_key_cannot_be_revoked(): void
    {
        $other = Tenant::create(['name' => 'Globex', 'slug' => 'globex', 'is_active' => true]);
        $theirAgent = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
            'tenant_id' => $other->id,
        ]);
        $this->keyFor($theirAgent);
        $theirKey = ApiKey::where('tenant_id', $other->id)->firstOrFail();

        // This install's admin has no tenant, so the key is not theirs to revoke
        $this->actingAs($this->admin)
            ->delete(route('api-keys.destroy', $theirKey->id))
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $theirKey->id]);
    }

    public function test_another_tenants_key_is_not_listed(): void
    {
        $other = Tenant::create(['name' => 'Globex', 'slug' => 'globex', 'is_active' => true]);
        $theirAgent = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
            'tenant_id' => $other->id,
        ]);
        $this->keyFor($theirAgent);
        $this->keyFor($this->admin);

        $this->actingAs($this->admin)
            ->get(route('api-keys.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('keys', 1));
    }

    public function test_an_agent_without_the_permission_cannot_manage_keys(): void
    {
        $agent = User::factory()->create([
            'role_id' => Role::where('name', Role::AGENT)->first()->id,
        ]);

        $this->actingAs($agent)->get(route('api-keys.index'))->assertForbidden();
    }

    public function test_a_bearer_key_authenticates_api_requests(): void
    {
        $plain = $this->keyFor($this->admin);
        Ticket::create(['subject' => 'Visible', 'requester_name' => 'A', 'requester_email' => 'a@test.com']);

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_read_only_key_cannot_write(): void
    {
        $plain = $this->keyFor($this->admin, ['tickets:read']);

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/tickets')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->postJson('/api/v1/tickets', [
                'subject' => 'Should not be created',
                'requester_name' => 'A',
                'requester_email' => 'a@test.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tickets', ['subject' => 'Should not be created']);
    }

    public function test_a_write_key_can_create_tickets(): void
    {
        $plain = $this->keyFor($this->admin, ['tickets:write']);

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->postJson('/api/v1/tickets', [
                'subject' => 'Created by integration',
                'requester_name' => 'A',
                'requester_email' => 'a@test.com',
            ])
            ->assertCreated();
    }

    public function test_an_expired_key_is_rejected(): void
    {
        $plain = $this->keyFor($this->admin, ['tickets:read'], now()->subDay());

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/tickets')
            ->assertUnauthorized();
    }

    /**
     * The per-key expiry is only meaningful if no global expiration overrides
     * it — sanctum.expiration does exactly that when set.
     */
    public function test_sanctum_has_no_global_expiration_overriding_per_key_expiry(): void
    {
        $this->assertNull(config('sanctum.expiration'));
    }
}
