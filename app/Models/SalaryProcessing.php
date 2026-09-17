<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SalaryProcessing extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'processed_at' => 'datetime',
        'paid_at' => 'date',
        'payment_updated_at' => 'datetime',
    ];

    /**
     * Payment lifecycle, in the order a payment normally moves through.
     * The key is the stored value; the tone drives the pill colour.
     */
    public const PAYMENT_STATUSES = [
        'Pending' => ['tone' => 'pending', 'icon' => 'bi-hourglass-split', 'hint' => 'Not started'],
        'Processing' => ['tone' => 'processing', 'icon' => 'bi-arrow-repeat', 'hint' => 'Transfer initiated'],
        'On Hold' => ['tone' => 'hold', 'icon' => 'bi-pause-circle', 'hint' => 'Held back deliberately'],
        'Partially Paid' => ['tone' => 'partial', 'icon' => 'bi-pie-chart', 'hint' => 'Part of the salary paid'],
        'Paid' => ['tone' => 'paid', 'icon' => 'bi-check-circle', 'hint' => 'Fully settled'],
        'Failed' => ['tone' => 'failed', 'icon' => 'bi-x-circle', 'hint' => 'Transfer bounced or rejected'],
    ];

    /** Statuses that carry money already handed over. */
    public const SETTLING_STATUSES = ['Paid', 'Partially Paid'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalaryProcessingLine::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function paymentUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_updated_by');
    }

    public function periodLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function balance(): float
    {
        return max(0, round((float) $this->net_salary - (float) $this->paid_amount, 2));
    }

    public function paymentTone(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status]['tone'] ?? 'pending';
    }

    public function paymentIcon(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status]['icon'] ?? 'bi-circle';
    }
}
