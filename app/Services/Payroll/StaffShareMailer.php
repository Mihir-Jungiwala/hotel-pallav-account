<?php

namespace App\Services\Payroll;

use App\Mail\StaffDetailsMail;
use App\Models\Employee;
use App\Models\PayrollMasterItem;
use App\Support\PayrollMasters;
use App\Support\PayrollPdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails one member of staff's details to an address kept in Payroll Master.
 *
 * The address is never typed at the point of sending: it must be a live entry
 * in the Share recipients list that the employee's company can see (shared
 * with every company, or its own), so details only ever go where the
 * SuperAdmin has approved. Nothing here throws; it returns a sentence for the
 * person who clicked.
 */
class StaffShareMailer
{
    /**
     * @return array{sent: bool, message: string}
     */
    public function send(Employee $employee, int $recipientId, string $sharedBy): array
    {
        $employee->loadMissing('company');
        $result = $this->attempt($employee, $recipientId, $sharedBy, $recipient);

        \App\Support\PayrollLogger::email('Staff details shared', $employee, (string) $recipient?->label, $recipient?->value, $result['sent'], $result['message'],
            ['Shared by' => $sharedBy, 'Attachment' => $result['sent'] ? 'employee-'.$employee->employee_code.'.pdf' : null]);

        return $result;
    }

    /** @return array{sent: bool, message: string} */
    private function attempt(Employee $employee, int $recipientId, string $sharedBy, ?PayrollMasterItem &$recipient = null): array
    {
        $employee->loadMissing('company', 'deductions.deduction', 'idProofType');
        $company = $employee->company;

        $recipient = PayrollMasters::active('share_emails', $company)->firstWhere('id', $recipientId);

        if ($recipient === null || blank($recipient->value)) {
            return ['sent' => false, 'message' => 'That recipient is not on the list for this company. Ask the SuperAdmin to add it under Payroll Master.'];
        }

        try {
            $pdf = PayrollPdf::make('payroll.pdf.employee', ['employee' => $employee])->output();

            Mail::to($recipient->value, $recipient->label)->send(
                new StaffDetailsMail($employee, $pdf, 'employee-'.$employee->employee_code.'.pdf', $recipient->label, $sharedBy)
            );
        } catch (\Throwable $e) {
            \App\Support\PayrollLogger::failure($e, 'Staff details email', $employee, $employee->name.' ('.$employee->employee_code.')', $employee->payroll_company_id);
            Log::warning('Staff details email failed', ['employee' => $employee->id, 'recipient' => $recipient->id, 'error' => $e->getMessage()]);

            return ['sent' => false, 'message' => 'The details could not be emailed. Check the mail settings and try again.'];
        }

        return ['sent' => true, 'message' => $employee->name."'s details emailed to {$recipient->label} ({$recipient->value})."];
    }
}
