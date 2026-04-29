<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function build(): self
    {
        return $this->subject('【校园艺术设计】验证码')
            ->view('emails.verification-code')
            ->with(['code' => $this->code]);
    }
}