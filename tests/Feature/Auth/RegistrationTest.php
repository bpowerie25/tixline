<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_disabled_by_default(): void
    {
        config(['support.agent_registration' => false]);

        $this->refreshRoutes();

        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_registration_post_disabled_by_default(): void
    {
        config(['support.agent_registration' => false]);

        $this->refreshRoutes();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
    }

    public function test_registration_screen_can_be_rendered_when_enabled(): void
    {
        config(['support.agent_registration' => true]);

        $this->refreshRoutes();

        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register_when_enabled(): void
    {
        config(['support.agent_registration' => true]);

        $this->refreshRoutes();

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_portal_registration_unaffected(): void
    {
        config(['support.agent_registration' => false]);

        $this->refreshRoutes();

        $response = $this->get('/portal/register');

        $response->assertStatus(200);
    }

    protected function refreshRoutes(): void
    {
        // Re-load routes so the config guard in auth.php is re-evaluated
        app('router')->getRoutes()->refreshNameLookups();

        $this->app['router'] = app('router');

        require base_path('routes/auth.php');
        require base_path('routes/web.php');
    }
}
