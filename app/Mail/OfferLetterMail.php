<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The appointment letter, sent to a new joiner with the PDF attached.
 *
 * The PDF is rendered by the caller and passed in as bytes, so what is sent
 * is exactly what the same "Generate Letter" button would have produced.
 */
class OfferLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $pdf,
        public string $filename,
    ) {}

    public function build(): self
    {
        $company = $this->employee->company;

        $mail = $this->subject('Your appointment letter from '.$company->name)
            ->view('emails.offer-letter', ['employee' => $this->employee, 'company' => $company])
            ->attachData($this->pdf, $this->filename, ['mime' => 'application/pdf']);

        // Replies go to the company, not to the system's no-reply address
        if ($company->email) {
            $mail->replyTo($company->email, $company->name);
        }

        return $mail;
    }
}
