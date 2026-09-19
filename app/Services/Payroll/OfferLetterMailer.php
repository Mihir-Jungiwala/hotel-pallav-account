<?php

namespace App\Services\Payroll;

use App\Mail\OfferLetterMail;
use App\Models\Employee;
use App\Models\JoiningLetter;
use App\Support\PayrollPdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * Emails a new joiner their appointment letter.
 *
 * Adding a member of staff must never fail because an email did not go, so
 * nothing here throws: it returns whether it worked and a sentence to show
 * the person who clicked, whichever way it went.
 */
class OfferLetterMailer
{
    /**
     * @return array{sent: bool, message: string}
     */
    public function send(Employee $employee): array
    {
        $result = $this->attempt($employee);

        \App\Support\PayrollLogger::email('Appointment letter', $employee, $employee->name, $employee->email, $result['sent'], $result['message'],
            ['Attachment' => $result['sent'] ? 'appointment-letter-'.$employee->employee_code.'.pdf' : null]);

        return $result;
    }

    /** @return array{sent: bool, message: string} */
    private function attempt(Employee $employee): array
    {
        $employee->loadMissing('company');
        $company = $employee->company;

        if (blank($employee->email)) {
            return ['sent' => false, 'message' => 'There is no email address on record, so the letter was not sent.'];
        }

        $letter = JoiningLetter::where('payroll_company_id', $company->id)->where('is_active', true)->first();

        if ($letter === null) {
            return ['sent' => false, 'message' => 'No active joining letter template is set up, so no letter was sent.'];
        }

        try {
            $pdf = PayrollPdf::make('payroll.pdf.joining-letter', [
                'letter' => $letter, 'employee' => $employee, 'company' => $company,
            ])->output();

            Mail::to($employee->email, $employee->name)->send(
                new OfferLetterMail($employee, $pdf, 'appointment-letter-'.$employee->employee_code.'.pdf')
            );
        } catch (\Throwable $e) {
            // Logged for whoever runs the server; the person clicking gets a
            // plain sentence, not a stack trace
            \App\Support\PayrollLogger::failure($e, 'Appointment letter email', $employee, $employee->name.' ('.$employee->employee_code.')', $employee->payroll_company_id);
            Log::warning('Offer letter email failed', ['employee' => $employee->id, 'error' => $e->getMessage()]);

            return ['sent' => false, 'message' => 'The letter could not be emailed. Check the mail settings, or download it and send it yourself.'];
        }

        return ['sent' => true, 'message' => 'Appointment letter emailed to '.$employee->email.'.'];
    }
}
