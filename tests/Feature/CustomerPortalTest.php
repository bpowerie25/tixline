<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_login_page_loads(): void
    {
        $this->get(route('portal.login'))->assertOk();
    }

    public function test_portal_register_page_loads(): void
    {
        $this->get(route('portal.register'))->assertOk();
    }

    public function test_customer_can_register(): void
    {
        $this->post(route('portal.register.submit'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('portal.tickets'));

        $this->assertDatabaseHas('customers', ['email' => 'jane@example.com']);
    }

    public function test_customer_can_login(): void
    {
        Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->post(route('portal.login.submit'), [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('portal.tickets'));
    }

    public function test_invalid_login_fails(): void
    {
        $this->post(route('portal.login.submit'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }

    public function test_unauthenticated_customer_redirected(): void
    {
        $this->get(route('portal.tickets'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_customer_sees_own_tickets(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        Ticket::create(['subject' => 'My ticket', 'requester_name' => 'Jane', 'requester_email' => 'jane@example.com']);
        Ticket::create(['subject' => 'Other ticket', 'requester_name' => 'Bob', 'requester_email' => 'bob@example.com']);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.tickets'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Tickets')
                ->has('tickets.data', 1)
            );
    }

    public function test_customer_can_create_ticket(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.tickets.store'), [
                'subject' => 'Need help',
                'body' => 'Description here',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Need help',
            'requester_email' => 'jane@example.com',
        ]);
    }

    public function test_customer_can_view_own_ticket(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $ticket = Ticket::create(['subject' => 'My ticket', 'requester_name' => 'Jane', 'requester_email' => 'jane@example.com']);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.ticket', $ticket))
            ->assertOk();
    }

    public function test_customer_cannot_view_others_ticket(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $ticket = Ticket::create(['subject' => 'Other', 'requester_name' => 'Bob', 'requester_email' => 'bob@example.com']);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.ticket', $ticket))
            ->assertForbidden();
    }

    public function test_customer_can_reply_to_own_ticket(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $ticket = Ticket::create(['subject' => 'My ticket', 'requester_name' => 'Jane', 'requester_email' => 'jane@example.com']);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.ticket.reply', $ticket), [
                'body' => 'Customer reply',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'ticket_id' => $ticket->id,
            'body' => 'Customer reply',
        ]);
    }

    public function test_reply_reopens_resolved_ticket(): void
    {
        $customer = Customer::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        $ticket = Ticket::create([
            'subject' => 'Resolved',
            'requester_name' => 'Jane',
            'requester_email' => 'jane@example.com',
            'status' => 'resolved',
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('portal.ticket.reply', $ticket), [
                'body' => 'Not fixed',
            ]);

        $this->assertEquals('open', $ticket->fresh()->status);
    }
}
