<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('jane@example.com|127.0.0.1');
    }

    public function test_locks_out_after_5_failed_attempts(): void
    {
        Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('portal.login.submit'), [
                'email' => 'jane@example.com',
                'password' => 'wrong-password',
            ]);
        }

        // 6th attempt should be rate-limited
        $response = $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts',
            session('errors')->first('email')
        );
    }

    public function test_throttle_keys_on_email_plus_ip(): void
    {
        Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        // Fill up rate limit for jane@example.com from one IP
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('portal.login.submit'), [
                'email' => 'jane@example.com',
                'password' => 'wrong',
            ]);
        }

        // jane@example.com should now be locked out
        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many', session('errors')->first('email'));

        // Different email from same IP should NOT be locked out
        // (gets a credential error, not a throttle error)
        $response = $this->post(route('portal.login.submit'), [
            'email' => 'other@example.com',
            'password' => 'wrong',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('Too many', session('errors')->first('email'));
    }

    public function test_successful_login_clears_rate_limit(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('correct'),
        ]);

        // Fail 4 times
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('portal.login.submit'), [
                'email' => 'jane@example.com',
                'password' => 'wrong',
            ]);
        }

        // Succeed — should clear rate limit
        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'correct',
        ])->assertRedirect(route('portal.tickets'));

        // Log out and try again — should not be locked out
        $this->post(route('portal.logout'));

        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        // Check it's a credential error, not a rate limit error
        $this->assertStringNotContainsString(
            'Too many',
            session('errors')->first('email')
        );
    }
}
