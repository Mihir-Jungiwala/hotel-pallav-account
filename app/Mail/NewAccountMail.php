<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A new account's sign-in details: the username and the one-off password.
 *
 * The password is generated, never chosen by whoever created the account and
 * never stored anywhere readable, so this email is the only place it appears.
 * It stops working the moment they replace it, which the app makes them do
 * before they can reach anything.
 */
class NewAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
        public bool $isReset = false,
    ) {}

    public function build(): self
    {
        return $this->subject($this->isReset
                ? 'Your password for Hotel Pallav Management Suite has been reset'
                : 'Your Hotel Pallav Management Suite account')
            ->view('emails.new-account', [
                'user' => $this->user,
                'password' => $this->temporaryPassword,
                'isReset' => $this->isReset,
                'url' => route('login'),
            ]);
    }
}
