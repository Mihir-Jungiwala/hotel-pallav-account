<?php

namespace App\Observers;

use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\BonusIncentive;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\ExperienceLetter;
use App\Models\JoiningLetter;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\PayrollMasterItem;
use App\Models\SalaryProcessing;
use App\Support\PayrollLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Logs every create, change and delete on the payroll records, with the full
 * detail: a new or removed record's fields, and for a change each field's old
 * and new value. One observer serves every payroll model.
 */
class PayrollModelObserver
{
    /** Never written to the log, even when they change. */
    private const SKIP = ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token', 'password', 'batch_id'];

    /** Model => [what it is called, how one is named, which column holds its company (null: itself)] */
    public static function models(): array
    {
        return [
            PayrollCompany::class => ['Company', fn ($m) => $m->name, null],
            Employee::class => ['Staff', fn ($m) => $m->name.' ('.$m->employee_code.')', 'payroll_company_id'],
            Deduction::class => ['Deduction', fn ($m) => $m->name, 'payroll_company_id'],
            AttendanceStatus::class => ['Attendance status', fn ($m) => $m->name, 'payroll_company_id'],
            AttendanceMonth::class => ['Attendance month', fn ($m) => $m->month.'/'.$m->year, 'payroll_company_id'],
            JoiningLetter::class => ['Joining letter', fn ($m) => 'Joining letter template', 'payroll_company_id'],
            ExperienceLetter::class => ['Experience letter', fn ($m) => 'Experience letter template', 'payroll_company_id'],
            PayrollAdvance::class => ['Advance', fn ($m) => optional($m->employee)->name ?: 'Advance #'.$m->id, 'payroll_company_id'],
            BonusIncentive::class => ['Bonus / incentive', fn ($m) => optional($m->employee)->name ?: 'Entry #'.$m->id, 'payroll_company_id'],
            EmployeeSeparation::class => ['Resignation', fn ($m) => optional($m->employee)->name ?: 'Exit #'.$m->id, 'payroll_company_id'],
            SalaryProcessing::class => ['Salary slip', fn ($m) => $m->employee_name.' - '.$m->periodLabel(), 'payroll_company_id'],
            \App\Models\FoodChargeRate::class => ['Food charge rate', fn ($m) => 'Rs '.$m->monthly_amount.' from '.$m->effective_from->format('M Y'), 'payroll_company_id'],
            PayrollMasterItem::class => ['Payroll Master', fn ($m) => Str::headline($m->list).': '.$m->label, 'payroll_company_id'],
        ];
    }

    public function created(Model $model): void
    {
        $this->write('created', $model, $this->snapshot($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = [];

        foreach ($model->getChanges() as $field => $new) {
            if ($this->skipped($field)) {
                continue;
            }

            $changes[$this->label($field)] = ['was' => $this->show($model->getRawOriginal($field)), 'became' => $this->show($new)];
        }

        if ($changes) {
            $this->write('updated', $model, $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->write('deleted', $model, $this->snapshot($model->getAttributes()));
    }

    private function write(string $action, Model $model, array $details): void
    {
        [$entity, $name, $companyColumn] = self::models()[$model::class];

        try {
            $label = (string) $name($model);
        } catch (\Throwable) {
            $label = '#'.$model->getKey();
        }

        $companyId = $companyColumn ? $model->{$companyColumn} : $model->getKey();

        $summary = match ($action) {
            'created' => "{$entity} added: {$label}",
            'deleted' => "{$entity} deleted: {$label}",
            default => "{$entity} changed: {$label} - ".collect($details)->keys()->take(4)->implode(', ')
                .(count($details) > 4 ? ' and '.(count($details) - 4).' more' : ''),
        };

        PayrollLogger::record($action, $entity, $summary, $details, $model, $label, $companyId ? (int) $companyId : null);
    }

    /** A record's own fields, readable: no bookkeeping, no empty values, no bare ids. */
    private function snapshot(array $attributes): array
    {
        $out = [];

        foreach ($attributes as $field => $value) {
            if ($this->skipped($field) || $value === null || $value === '' || Str::endsWith($field, '_id')) {
                continue;
            }

            $out[$this->label($field)] = $this->show($value);
        }

        return $out;
    }

    private function skipped(string $field): bool
    {
        return in_array($field, self::SKIP, true);
    }

    private function label(string $field): string
    {
        return Str::headline(Str::replaceLast('_id', '', $field));
    }

    private function show(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return Str::limit(is_scalar($value) ? (string) $value : json_encode($value), 300);
    }
}
