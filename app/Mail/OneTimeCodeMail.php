<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Auth\OneTimeCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OneTimeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public string $purpose,
    ) {}

    public function build(): self
    {
        $subject = $this->purpose === OneTimeCode::RESET
            ? 'Your Hotel Pallav password reset code'
            : 'Your Hotel Pallav sign-in code';

        return $this->subject($subject)
            ->view('emails.one-time-code', [
                'user' => $this->user,
                'code' => $this->code,
                'purpose' => $this->purpose,
                'minutes' => OneTimeCode::VALID_MINUTES,
            ]);
    }
}
