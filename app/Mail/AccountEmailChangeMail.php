<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountEmailChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $verificationUrl, public string $firstName) {}

    public function build(): static
    {
        return $this->subject('Confirm your new PeerHive email address')
            ->view('emails.account-email-change');
    }
}
