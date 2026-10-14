<?php

namespace App\Services\Mail;

use App\Mail\CommonMail;
use Illuminate\Support\Facades\Mail;

class MailService
{
    public function send(
        string $to,
        string $title,
        string $message,
        string $name,
        ?string $otp = null,
        ?int $expiry = null,
    ): void {
        Mail::to($to)->send(
            new CommonMail(
                title: $title,
                bodyMessage: $message,
                name: $name,
                otp: $otp,
                expiry: $expiry,
            )
        );
    }
}