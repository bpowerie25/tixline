<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_create_form_with_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('forms.store'), [
                'name' => 'Bug Report',
                'description' => 'Report a bug',
                'is_active' => true,
                'fields' => [
                    [
                        'name' => 'category',
                        'label' => 'Category',
                        'type' => 'select',
                        'options' => ['UI', 'Backend', 'API'],
                        'is_required' => true,
                        'sort_order' => 0,
                    ],
                    [
                        'name' => 'browser',
                        'label' => 'Browser',
                        'type' => 'text',
                        'is_required' => false,
                        'sort_order' => 1,
                        'conditions' => ['field' => 'category', 'operator' => 'equals', 'value' => 'UI'],
                    ],
                ],
            ])
            ->assertRedirect();

        $form = Form::where('name', 'Bug Report')->first();
        $this->assertNotNull($form);
        $this->assertEquals(2, $form->fields()->count());
        $this->assertEquals('bug-report', $form->slug);

        $browser = $form->fields()->where('name', 'browser')->first();
        $this->assertNotNull($browser->conditions);
        $this->assertEquals('category', $browser->conditions['field']);
    }

    public function test_update_form_replaces_fields(): void
    {
        $form = Form::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $form->fields()->create(['name' => 'old_field', 'label' => 'Old', 'type' => 'text', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->put(route('forms.update', $form), [
                'name' => 'Updated Form',
                'is_active' => true,
                'fields' => [
                    ['name' => 'new_field', 'label' => 'New Field', 'type' => 'text', 'sort_order' => 0],
                ],
            ])
            ->assertRedirect();

        $form->refresh();
        $this->assertEquals('Updated Form', $form->name);
        $this->assertEquals(1, $form->fields()->count());
        $this->assertEquals('new_field', $form->fields()->first()->name);
    }

    public function test_create_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('forms.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Forms/Show'));
    }

    public function test_delete_form_cascades_fields(): void
    {
        $form = Form::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $form->fields()->create(['name' => 'f', 'label' => 'F', 'type' => 'text', 'sort_order' => 0]);

        $this->actingAs($this->admin)
            ->delete(route('forms.destroy', $form))
            ->assertRedirect();

        $this->assertDatabaseMissing('forms', ['id' => $form->id]);
        $this->assertDatabaseMissing('form_fields', ['form_id' => $form->id]);
    }
}
