<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAuthTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
    }

    protected function setTenant(?Tenant $tenant = null): void
    {
        if ($tenant) {
            app()->instance('tenant', $tenant);
        } else {
            app()->forgetInstance('tenant');
        }
    }

    public function test_customer_with_null_tenant_id_can_login(): void
    {
        // Simulate a customer created before tenant scoping was fixed (NULL tenant_id)
        $customer = Customer::withoutGlobalScopes()->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);
        // Force tenant_id to NULL (bypassing BelongsToTenant creating hook)
        $customer->updateQuietly(['tenant_id' => null]);

        $this->setTenant($this->tenant);

        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('portal.tickets'));
    }

    public function test_customer_with_tenant_id_can_login(): void
    {
        $this->setTenant($this->tenant);

        Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('portal.tickets'));
    }

    public function test_password_reset_updates_password_with_tenant_scope(): void
    {
        $this->setTenant($this->tenant);

        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'old-password',
            'tenant_id' => $this->tenant->id,
        ]);

        // Request password reset
        $this->post(route('portal.forgot-password.submit'), [
            'email' => 'jane@example.com',
        ]);

        // Get the token from the database
        $reset = \Illuminate\Support\Facades\DB::table('customer_password_resets')
            ->where('email', 'jane@example.com')
            ->first();
        $this->assertNotNull($reset, 'Password reset record should exist');

        // We can't get the plain token back from the hash, so let's test the
        // actual reset by inserting a known token
        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\DB::table('customer_password_resets')
            ->where('email', 'jane@example.com')
            ->update(['token' => Hash::make($token)]);

        // Reset the password
        $this->post(route('portal.reset-password.submit'), [
            'token' => $token,
            'email' => 'jane@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('portal.login'));

        // Verify the password was actually updated
        $customer->refresh();
        $this->assertTrue(Hash::check('new-password', $customer->password));
    }

    public function test_password_reset_works_for_customer_with_null_tenant_id(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'old-password',
        ]);
        $customer->updateQuietly(['tenant_id' => null]);

        $this->setTenant($this->tenant);

        // Insert a known reset token
        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\DB::table('customer_password_resets')->insert([
            'email' => 'jane@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->post(route('portal.reset-password.submit'), [
            'token' => $token,
            'email' => 'jane@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('portal.login'));

        // Verify password was actually updated
        $customer->refresh();
        $this->assertTrue(Hash::check('new-password', $customer->password));
    }

    public function test_login_records_last_login_at(): void
    {
        $this->setTenant($this->tenant);

        Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $customer = Customer::withoutGlobalScopes()->where('email', 'jane@example.com')->first();
        $this->assertNotNull($customer->last_login_at);
    }
}
