<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Every payroll record belongs to exactly one company, and a request must
 * never be allowed to touch a record outside the company currently active in
 * the session - not through a crafted URL, not through a form's hidden ID,
 * not through anything. Route-model binding resolves a record by its ID alone,
 * so each controller that receives one of these models calls this first.
 *
 * A mismatch aborts as 404, the same as "this ID doesn't exist" - it never
 * confirms that a record exists in a company the requester can't see.
 */
class PayrollScope
{
    public static function ensure(mixed $record): void
    {
        $current = PayrollContext::current();

        abort_if($current === null, 404);
        abort_unless(
            $record !== null && (int) $record->payroll_company_id === $current->id,
            404
        );
    }

    /**
     * A validation rule for a foreign key pointing at another payroll table.
     *
     * A bare "exists:employees,id" only proves the row exists somewhere in the
     * system, so a posted employee_id belonging to a different company would
     * pass and attach that company's employee to this one's record. Every
     * payroll foreign key is checked against the active company instead.
     */
    public static function belongsToCompany(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('payroll_company_id', PayrollContext::currentOrFail()->id);
    }
}
