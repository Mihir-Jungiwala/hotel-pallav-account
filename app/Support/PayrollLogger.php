<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\PayrollCompany;
use App\Models\PayrollLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Writes the payroll log. Every entry says who did what, to which record, in
 * which company, and carries the full detail: what changed (was / became), what
 * a new or removed record held, or what an email was and where it went.
 *
 * Logging must never break the work it describes, so nothing here throws.
 */
class PayrollLogger
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function record(
        string $action,
        string $entity,
        string $summary,
        array $details = [],
        ?Model $subject = null,
        ?string $subjectLabel = null,
        ?int $companyId = null,
        string $status = 'success',
    ): void {
        try {
            $companyId ??= PayrollContext::current()?->id;
            $user = Auth::user();

            PayrollLog::create([
                'payroll_company_id' => $companyId,
                'company_name' => $companyId ? PayrollCompany::whereKey($companyId)->value('name') : null,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'action' => $action,
                'status' => $status,
                'entity' => $entity,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subjectLabel,
                'summary' => mb_substr($summary, 0, 500),
                'details' => $details ?: null,
                'ip_address' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Payroll log entry could not be written', ['error' => $e->getMessage()]);
        }
    }

    /**
     * A technical failure, with what a developer needs to find it: the exception,
     * where it was thrown, the call trail, and what was being asked of the app.
     * Passwords and tokens in the request are never written.
     */
    public static function failure(\Throwable $e, string $context, ?Model $subject = null, ?string $subjectLabel = null, ?int $companyId = null): void
    {
        try {
            $request = request();
            // The app's own frames first; if the fault came from deep inside a library, its frames instead
            $frames = collect($e->getTrace())->filter(fn ($f) => isset($f['file']));
            $own = $frames->filter(fn ($f) => ! str_contains($f['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR));
            $trace = ($own->isNotEmpty() ? $own : $frames)
                ->take(8)
                ->map(fn ($f) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $f['file']).':'.($f['line'] ?? '?').'  '.(isset($f['class']) ? class_basename($f['class']).'::' : '').($f['function'] ?? ''))
                ->implode("\n");

            $input = collect($request?->except(['password', 'password_confirmation', '_token', 'current_password']) ?? [])
                ->map(fn ($v) => is_scalar($v) ? \Illuminate\Support\Str::limit((string) $v, 80) : '['.gettype($v).']')
                ->take(30)->all();

            self::record(
                'error',
                $context,
                $context.' failed: '.class_basename($e).' - '.\Illuminate\Support\Str::limit($e->getMessage(), 200),
                array_filter([
                    'Exception' => $e::class,
                    'Message' => $e->getMessage(),
                    'Thrown at' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()).':'.$e->getLine(),
                    'Previous' => $e->getPrevious() ? get_class($e->getPrevious()).': '.$e->getPrevious()->getMessage() : null,
                    'Call trail' => $trace,
                    'Request' => $request ? $request->method().' '.$request->fullUrl() : null,
                    'Route' => $request?->route()?->getName(),
                    'Form fields' => $input ? json_encode($input, JSON_UNESCAPED_UNICODE) : null,
                    'Browser' => $request ? \Illuminate\Support\Str::limit((string) $request->userAgent(), 160) : null,
                    'PHP' => PHP_VERSION.' / Laravel '.app()->version(),
                ], fn ($v) => filled($v)),
                $subject,
                $subjectLabel,
                $companyId,
                'failed',
            );
        } catch (\Throwable) {
            // Logging a failure must never cause another one
        }
    }

    /** An email the payroll module sent, or tried to. */
    public static function email(string $kind, Employee $employee, string $toName, ?string $toAddress, bool $sent, string $message, array $extra = []): void
    {
        $who = $employee->name.' ('.$employee->employee_code.')';

        self::record(
            'emailed',
            'Email',
            ($sent ? '' : 'Not sent: ')."{$kind} for {$employee->name}".($toAddress ? " to {$toAddress}" : ''),
            array_filter([
                'Email' => $kind,
                'Staff' => $who,
                'Sent to' => $toName ? "{$toName} <{$toAddress}>" : $toAddress,
                'Outcome' => $sent ? 'Sent' : 'Not sent',
                'Message' => $message,
            ] + $extra, fn ($v) => filled($v)),
            $employee,
            $who,
            $employee->payroll_company_id,
            $sent ? 'success' : 'failed',
        );
    }
}
