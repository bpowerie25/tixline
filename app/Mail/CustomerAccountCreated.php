<?php

namespace App\Mail;

use App\Helpers\EmailLayout;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerAccountCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $temporaryPassword,
        public string $ticketReference,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your support account has been created',
        );
    }

    public function build()
    {
        $name = e($this->customer->name);
        $password = e($this->temporaryPassword);
        $reference = e($this->ticketReference);
        $portalUrl = EmailLayout::portalUrl();

        $body = <<<HTML
        <h2 style="color: #1f2937; margin-top: 0;">Welcome, {$name}</h2>
        <p>A support account has been created for you so you can track and reply to your tickets.</p>
        <p>Your ticket reference: <strong>{$reference}</strong></p>
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />
        <p><strong>Your login details:</strong></p>
        <p>
            Email: <strong>{$this->customer->email}</strong><br />
            Temporary password: <strong>{$password}</strong>
        </p>
        <p style="color: #6b7280; font-size: 13px; margin-top: 24px;">
            We recommend changing your password after your first login.
        </p>
        HTML;

        return $this->html(EmailLayout::wrap($body, $portalUrl));
    }
}
