<?php

namespace Tests\Feature;

use App\Models\AiTaggingConfig;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTaggingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', Role::ADMIN)->first();
        $agentRole = Role::where('name', Role::AGENT)->first();

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->agent = User::factory()->create(['role_id' => $agentRole->id]);
    }

    // --- Route access ---

    public function test_settings_page_requires_auth(): void
    {
        $this->get(route('ai-tagging.index'))->assertRedirect(route('login'));
    }

    public function test_agent_cannot_access_settings(): void
    {
        $this->actingAs($this->agent)
            ->get(route('ai-tagging.index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('ai-tagging.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/AiTagging')
                ->has('providers')
                ->has('tags')
            );
    }

    // --- Config CRUD ---

    public function test_admin_can_create_config(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => 'test-api-key-123',
                'auto_apply' => false,
                'confidence_threshold' => 0.75,
                'flag_miscategorised' => true,
                'tag_on_create' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_tagging_configs', [
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'is_enabled' => true,
        ]);
    }

    public function test_admin_can_update_config(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => false,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'old-key',
            'confidence_threshold' => 0.700,
        ]);

        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'claude',
                'model' => 'claude-sonnet-4-20250514',
                'api_key' => '', // keep existing
                'auto_apply' => true,
                'confidence_threshold' => 0.85,
                'flag_miscategorised' => false,
                'tag_on_create' => false,
            ])
            ->assertRedirect();

        $config = AiTaggingConfig::first();
        $this->assertEquals('claude', $config->provider);
        $this->assertTrue($config->is_enabled);
        $this->assertTrue($config->auto_apply);
        // API key should be preserved when blank
        $this->assertNotEmpty($config->api_key);
    }

    public function test_admin_can_disable_ai_tagging(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'confidence_threshold' => 0.700,
        ]);

        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => false,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => '',
                'auto_apply' => false,
                'confidence_threshold' => 0.70,
                'flag_miscategorised' => true,
                'tag_on_create' => true,
            ])
            ->assertRedirect();

        $config = AiTaggingConfig::first();
        $this->assertFalse($config->is_enabled);
        $this->assertNull(AiTaggingConfig::active());
    }

    public function test_admin_can_re_enable_ai_tagging(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => false,
            'provider' => 'gemini',
            'model' => 'gemini-2.0-flash',
            'api_key' => 'test-key',
            'confidence_threshold' => 0.700,
        ]);

        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => '',
                'auto_apply' => false,
                'confidence_threshold' => 0.70,
                'flag_miscategorised' => true,
                'tag_on_create' => true,
            ])
            ->assertRedirect();

        $this->assertNotNull(AiTaggingConfig::active());
    }

    public function test_initial_setup_requires_api_key(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => '',
                'auto_apply' => false,
                'confidence_threshold' => 0.70,
                'flag_miscategorised' => true,
                'tag_on_create' => true,
            ])
            ->assertSessionHasErrors('api_key');
    }

    public function test_config_validates_provider(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'invalid_provider',
                'model' => 'some-model',
                'api_key' => 'key',
                'confidence_threshold' => 0.70,
            ])
            ->assertSessionHasErrors('provider');
    }

    public function test_config_validates_confidence_range(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => 'key',
                'confidence_threshold' => 1.5, // above max
            ])
            ->assertSessionHasErrors('confidence_threshold');
    }

    public function test_agent_cannot_save_config(): void
    {
        $this->actingAs($this->agent)
            ->post(route('ai-tagging.store'), [
                'is_enabled' => true,
                'provider' => 'gemini',
                'model' => 'gemini-2.0-flash',
                'api_key' => 'key',
                'confidence_threshold' => 0.70,
            ])
            ->assertForbidden();
    }

    // --- Tag CRUD ---

    public function test_admin_can_create_tag(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ai-tagging.tags.store'), [
                'name' => 'Billing Issue',
                'color' => '#ef4444',
                'description' => 'Problems with invoices or payments',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', [
            'name' => 'Billing Issue',
            'slug' => 'billing-issue',
            'color' => '#ef4444',
        ]);
    }

    public function test_duplicate_tag_name_rejected(): void
    {
        Tag::create(['name' => 'Billing', 'slug' => 'billing']);

        $this->actingAs($this->admin)
            ->post(route('ai-tagging.tags.store'), [
                'name' => 'Billing',
                'color' => '#ef4444',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_tag(): void
    {
        $tag = Tag::create(['name' => 'Billing', 'slug' => 'billing', 'color' => '#000000']);

        $this->actingAs($this->admin)
            ->put(route('ai-tagging.tags.update', $tag), [
                'name' => 'Billing & Payments',
                'color' => '#ef4444',
                'description' => 'Updated description',
            ])
            ->assertRedirect();

        $tag->refresh();
        $this->assertEquals('Billing & Payments', $tag->name);
        $this->assertEquals('billing-payments', $tag->slug);
    }

    public function test_admin_can_delete_tag(): void
    {
        $tag = Tag::create(['name' => 'Obsolete', 'slug' => 'obsolete']);

        $this->actingAs($this->admin)
            ->delete(route('ai-tagging.tags.destroy', $tag))
            ->assertRedirect();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_agent_cannot_create_tag(): void
    {
        $this->actingAs($this->agent)
            ->post(route('ai-tagging.tags.store'), [
                'name' => 'Test',
                'color' => '#000000',
            ])
            ->assertForbidden();
    }

    public function test_agent_cannot_delete_tag(): void
    {
        $tag = Tag::create(['name' => 'Protected', 'slug' => 'protected']);

        $this->actingAs($this->agent)
            ->delete(route('ai-tagging.tags.destroy', $tag))
            ->assertForbidden();
    }

    public function test_tag_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->post(route('ai-tagging.tags.store'), [
                'name' => '',
                'color' => '#000000',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_settings_page_shows_existing_tags(): void
    {
        Tag::create(['name' => 'Billing', 'slug' => 'billing']);
        Tag::create(['name' => 'Login', 'slug' => 'login']);

        $this->actingAs($this->admin)
            ->get(route('ai-tagging.index'))
            ->assertInertia(fn ($page) => $page->has('tags', 2));
    }

    public function test_settings_page_shows_existing_config(): void
    {
        AiTaggingConfig::create([
            'is_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'api_key' => 'secret',
            'confidence_threshold' => 0.800,
        ]);

        $this->actingAs($this->admin)
            ->get(route('ai-tagging.index'))
            ->assertInertia(fn ($page) => $page
                ->where('config.provider', 'openai')
                ->where('config.model', 'gpt-4o')
                ->where('config.has_api_key', true)
                ->missing('config.api_key') // key never sent to frontend
            );
    }
}
