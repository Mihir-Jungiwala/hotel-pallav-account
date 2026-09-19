<?php

namespace App\Support;

use App\Models\PayrollCompany;
use App\Models\PayrollMasterItem;
use Illuminate\Support\Collection;

/**
 * The payroll master lists, and what a company sees of them.
 *
 * An item saved with no company selected applies to every company; one saved
 * inside a company applies to that company alone. A company therefore sees
 * the shared items plus its own.
 */
class PayrollMasters
{
    /**
     * key => name, description, icon, and what the saved value is (null when
     * the label is all there is).
     */
    public const LISTS = [
        'share_emails' => [
            'name' => 'Share recipients',
            'description' => 'Email addresses staff details can be sent to from Staff Management.',
            'icon' => 'bi-envelope-at',
            'value' => 'Email address',
            'type' => 'email',
            'fixed' => false, 'defaults' => [],
        ],
        'designations' => [
            'name' => 'Designations',
            'description' => 'Suggested when choosing a designation for a member of staff.',
            'icon' => 'bi-briefcase',
            'value' => null,
            'type' => null,
            'fixed' => false, 'defaults' => [],
        ],
        'departments' => [
            'name' => 'Departments',
            'description' => 'Suggested when choosing a department for a member of staff.',
            'icon' => 'bi-diagram-3',
            'value' => null,
            'type' => null,
            'fixed' => false, 'defaults' => [],
        ],
        'gender' => [
            'name' => 'Gender',
            'description' => 'Choices on the staff record.',
            'icon' => 'bi-person',
            'value' => null, 'type' => null,
            'fixed' => false, 'defaults' => ['Male', 'Female', 'Other'],
        ],
        'separation_type' => [
            'name' => 'Exit types',
            'description' => 'Reasons a member of staff can leave, on Resignation.',
            'icon' => 'bi-box-arrow-right',
            'value' => null, 'type' => null,
            'fixed' => false, 'defaults' => ['Resignation', 'Termination', 'Retirement', 'End of Contract'],
        ],
        'id_proofs' => [
            'name' => 'ID proof types',
            'description' => 'Documents a member of staff can give as ID proof.',
            'icon' => 'bi-person-vcard',
            'value' => null, 'type' => null,
            'fixed' => false, 'defaults' => ['Aadhaar Card', 'PAN Card', 'Passport', 'Driving Licence', 'Voter ID'],
        ],
        'salary_payment_mode' => [
            'name' => 'Salary payment modes',
            'description' => 'How staff salary is paid. A mode named Bank asks for account details.',
            'icon' => 'bi-wallet2',
            'value' => null, 'type' => null,
            'fixed' => false, 'defaults' => ['Cash', 'Bank'],
        ],
    ];

    public static function exists(string $list): bool
    {
        return isset(self::LISTS[$list]);
    }

    /** Active items a company can use: shared ones first, then its own. */
    public static function active(string $list, ?PayrollCompany $company = null): Collection
    {
        return self::visible($list, $company)->where('is_active', true)->values();
    }

    /** Every item in scope, active or not, shared ones first. */
    public static function visible(string $list, ?PayrollCompany $company = null): Collection
    {
        return PayrollMasterItem::where('list', $list)
            ->where(fn ($q) => $q->whereNull('payroll_company_id')
                ->when($company, fn ($w) => $w->orWhere('payroll_company_id', $company->id)))
            ->orderByRaw('payroll_company_id is not null')
            ->orderBy('sort_order')->orderBy('label')
            ->get();
    }

    public static function isFixed(string $list): bool
    {
        return (bool) (self::LISTS[$list]['fixed'] ?? false);
    }

    /**
     * What a form offers for a list: the active entries the company can see,
     * or the built-in choices while none are set (and always for a fixed list).
     */
    public static function choices(string $list, ?PayrollCompany $company = null): array
    {
        $defaults = self::LISTS[$list]['defaults'] ?? [];

        if (self::isFixed($list)) {
            return $defaults;
        }

        $company ??= PayrollContext::current();

        return self::labels($list, $company) ?: $defaults;
    }

    /** Plain labels, for suggestion lists. */
    public static function labels(string $list, ?PayrollCompany $company = null): array
    {
        return self::active($list, $company)->pluck('label')->unique()->values()->all();
    }
}
