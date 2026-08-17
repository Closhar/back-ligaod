<?php

namespace App\Mail;

use App\Support\SiteMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetCode extends Mailable
{
    use Queueable, SerializesModels;

    public array $brand;

    public function __construct(public string $code)
    {
        $this->brand = SiteMailBranding::data();
    }

    public function build(): self
    {
        return $this->subject('Код для восстановления пароля')->view('emails.password-reset-code');
    }
}
