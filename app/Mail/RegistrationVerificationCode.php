<?php

namespace App\Mail;

use App\Support\SiteMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegistrationVerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public array $brand;

    public function __construct(public string $code)
    {
        $this->brand = SiteMailBranding::data();
    }

    public function build(): self
    {
        return $this->subject('Код подтверждения регистрации')->view('emails.registration-verification-code');
    }
}
