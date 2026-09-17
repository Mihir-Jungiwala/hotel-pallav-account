<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryUpdateHistory extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['effective_date' => 'date'];

    public const TRACKED_FIELDS = [
        'salary' => 'Salary',
        'designation' => 'Designation',
        'department' => 'Department',
        'daily_working_hours' => 'Working Hours',
        'payment_mode' => 'Salary Payment Type',
        'bank_name' => 'Bank Name',
        'account_holder_name' => 'Account Holder Name',
        'account_number' => 'Account Number',
        'ifsc_code' => 'IFSC Code',
        'branch_name' => 'Branch Name',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
