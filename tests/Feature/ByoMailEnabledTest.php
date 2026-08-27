<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Counterpart to the cloud's ByoMailDisabledTest: a self-hosted install still
 * gets the per-tenant mail settings screen, where it is a single process and
 * the boot-time config actually applies.
 */
class ByoMailEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_feature_is_on_by_default(): void
    {
        $this->assertTrue(config('support.features.byo_mail'));
    }

    public function test_the_mail_settings_screen_is_reachable(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);

        $this->actingAs($admin)->get(route('mail-config.index'))->assertOk();
    }

    public function test_turning_the_feature_off_hides_the_screen(): void
    {
        config(['support.features.byo_mail' => false]);

        $admin = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);

        $this->actingAs($admin)->get(route('mail-config.index'))->assertNotFound();
    }
}
