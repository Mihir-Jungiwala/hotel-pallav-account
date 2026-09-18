<?php

namespace App\Models;

use App\Models\Concerns\HasEntryNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAdvance extends Model
{
    use HasEntryNumber;

    protected $guarded = ['id'];

    protected $casts = [
        'advance_date' => 'datetime',
        'amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'recovered_amount' => 'decimal:2',
        'is_settled' => 'boolean',
        'is_carry_forward' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outstanding(): float
    {
        return max(0, (float) $this->amount - (float) $this->recovered_amount);
    }
}
