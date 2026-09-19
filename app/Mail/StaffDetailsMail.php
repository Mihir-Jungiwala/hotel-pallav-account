<?php

namespace App\Mail;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * One member of staff's details, shared with an address kept in Payroll Master.
 * The employee record PDF is attached, rendered by the caller, so what
 * arrives is what the View Record button shows.
 */
class StaffDetailsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $pdf,
        public string $filename,
        public string $recipientName,
        public string $sharedBy,
    ) {}

    public function build(): self
    {
        $company = $this->employee->company;

        return $this->subject('Staff details: '.$this->employee->name.' ('.$company->name.')')
            ->view('emails.staff-details', [
                'employee' => $this->employee, 'company' => $company,
                'recipientName' => $this->recipientName, 'sharedBy' => $this->sharedBy,
            ])
            ->attachData($this->pdf, $this->filename, ['mime' => 'application/pdf']);
    }
}
