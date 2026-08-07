<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerPasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resetUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password',
        );
    }

    public function build()
    {
        return $this->html(<<<HTML
        <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #1f2937;">Password Reset</h2>
            <p>You requested a password reset. Click the button below to set a new password:</p>
            <p>
                <a href="{$this->resetUrl}" style="display: inline-block; background: #be123c; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                    Reset Password
                </a>
            </p>
            <p style="color: #6b7280; font-size: 13px; margin-top: 24px;">
                This link expires in 60 minutes. If you didn't request this, you can ignore this email.
            </p>
        </div>
        HTML);
    }
}
