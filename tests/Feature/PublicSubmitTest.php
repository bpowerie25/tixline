<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_page_loads(): void
    {
        $this->get(route('submit.create'))->assertOk();
    }

    public function test_submit_page_with_form(): void
    {
        $form = Form::create(['name' => 'Bug Report', 'slug' => 'bug-report', 'is_active' => true]);
        $form->fields()->create(['name' => 'category', 'label' => 'Category', 'type' => 'select', 'sort_order' => 0]);

        $this->get(route('submit.create', ['form' => 'bug-report']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('form.id', $form->id));
    }

    public function test_submit_ticket(): void
    {
        $this->post(route('submit.store'), [
            'subject' => 'Public ticket',
            'body' => 'From the form',
            'requester_name' => 'Visitor',
            'requester_email' => 'visitor@example.com',
        ])->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/TicketConfirmation'));

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Public ticket',
            'requester_email' => 'visitor@example.com',
            'source' => 'web',
        ]);
    }

    public function test_submit_with_custom_fields(): void
    {
        $form = Form::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);

        $this->post(route('submit.store'), [
            'subject' => 'With custom fields',
            'requester_name' => 'Visitor',
            'requester_email' => 'visitor@example.com',
            'form_id' => $form->id,
            'custom_fields' => ['category' => 'Bug', 'severity' => 'High'],
        ]);

        $ticket = Ticket::where('subject', 'With custom fields')->first();
        $this->assertEquals('Bug', $ticket->custom_fields['category']);
    }

    public function test_submit_validation(): void
    {
        $this->post(route('submit.store'), [])
            ->assertSessionHasErrors(['subject', 'requester_name', 'requester_email']);
    }
}
