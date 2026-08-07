<?php

namespace App\Mail;

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
        $portalUrl = url('/portal/login');

        return $this->html(<<<HTML
        <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #1f2937;">Welcome, {$name}</h2>
            <p>A support account has been created for you so you can track and reply to your tickets.</p>
            <p>Your ticket reference: <strong>{$reference}</strong></p>
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />
            <p><strong>Your login details:</strong></p>
            <p>
                Email: <strong>{$this->customer->email}</strong><br />
                Temporary password: <strong>{$password}</strong>
            </p>
            <p>
                <a href="{$portalUrl}" style="display: inline-block; background: #be123c; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                    Sign in to the portal
                </a>
            </p>
            <p style="color: #6b7280; font-size: 13px; margin-top: 24px;">
                We recommend changing your password after your first login.
            </p>
        </div>
        HTML);
    }
}
