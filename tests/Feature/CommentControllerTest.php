<?php

namespace Tests\Feature;

use App\Mail\TicketReply;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;

    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'agent']);
        $this->ticket = Ticket::create([
            'subject' => 'Test ticket',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@test.com',
        ]);
    }

    public function test_add_reply(): void
    {
        Mail::fake();

        $this->actingAs($this->agent)
            ->post(route('tickets.comments.store', $this->ticket), [
                'body' => 'Here is your answer',
                'is_internal' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'ticket_id' => $this->ticket->id,
            'body' => 'Here is your answer',
            'type' => 'reply',
            'is_internal' => false,
        ]);

        Mail::assertQueued(TicketReply::class);
    }

    public function test_add_internal_note_does_not_email(): void
    {
        Mail::fake();

        $this->actingAs($this->agent)
            ->post(route('tickets.comments.store', $this->ticket), [
                'body' => 'Internal note',
                'is_internal' => true,
            ]);

        $this->assertDatabaseHas('comments', [
            'type' => 'note',
            'is_internal' => true,
        ]);

        Mail::assertNotQueued(TicketReply::class);
    }

    public function test_first_reply_sets_first_responded_at(): void
    {
        Mail::fake();
        $this->assertNull($this->ticket->first_responded_at);

        $this->actingAs($this->agent)
            ->post(route('tickets.comments.store', $this->ticket), [
                'body' => 'Reply',
                'is_internal' => false,
            ]);

        $this->assertNotNull($this->ticket->fresh()->first_responded_at);
    }

    public function test_internal_note_does_not_set_first_responded_at(): void
    {
        $this->actingAs($this->agent)
            ->post(route('tickets.comments.store', $this->ticket), [
                'body' => 'Note',
                'is_internal' => true,
            ]);

        $this->assertNull($this->ticket->fresh()->first_responded_at);
    }

    public function test_comment_with_attachment(): void
    {
        Mail::fake();
        Storage::fake('local');

        $this->actingAs($this->agent)
            ->post(route('tickets.comments.store', $this->ticket), [
                'body' => 'See attached',
                'is_internal' => false,
                'attachments' => [
                    UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
                ],
            ]);

        $comment = $this->ticket->comments()->first();
        $this->assertEquals(1, $comment->attachments()->count());

        $attachment = $comment->attachments()->first();
        $this->assertEquals('document.pdf', $attachment->original_filename);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_requires_body(): void
    {
        $this->actingAs($this->agent)
            ->post(route('tickets.comments.store', $this->ticket), [
                'body' => '',
            ])
            ->assertSessionHasErrors('body');
    }
}
