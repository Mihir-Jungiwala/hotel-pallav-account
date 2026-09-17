<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillMasterBill extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'bill_date' => 'date',
        'debit_bill_date' => 'date',
        'debit_bill_date_1' => 'date',
        'debit_bill_date_2' => 'date',
        'debit_bill_date_3' => 'date',
        'debit_bill_date_4' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(BillMasterAdvance::class, 'advance_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDebit(): bool
    {
        return strtolower((string) $this->hotel_mode_of_payment) === 'debit'
            || strtolower((string) $this->food_mode_of_payment) === 'debit';
    }

    public function hasDebitBillRecorded(): bool
    {
        return $this->debit_bill_date !== null;
    }
}
