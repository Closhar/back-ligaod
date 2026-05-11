<?php

namespace App\Mail;

use App\Support\SiteMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $verificationUrl;
    public array $brand;

    public function __construct($verificationUrl)
    {
        $this->verificationUrl = $verificationUrl;
        $this->brand = SiteMailBranding::data();
    }

    public function build()
    {
        return $this->subject('Подтверждение почты')
            ->view('emails.verify-email');
    }
}
