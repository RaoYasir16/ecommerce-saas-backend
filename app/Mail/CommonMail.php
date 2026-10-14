<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CommonMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $bodyMessage,
        public string $name,
        public ?string $otp = null,
        public ?int $expiry = null,
    ) {}

    public function build(): static
    {
        return $this->subject($this->title)
            ->view('emails.common', [
                'title' => $this->title,
                'bodyMessage' => $this->bodyMessage,
                'name' => $this->name,
                'otp' => $this->otp,
                'expiry' => $this->expiry,
            ]);
    }
}