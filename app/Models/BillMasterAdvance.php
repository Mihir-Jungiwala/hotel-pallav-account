<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillMasterAdvance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'refund_payment_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(CompanyProfile::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(BillMasterBill::class, 'advance_id');
    }

    public function isUnused(): bool
    {
        return (float) $this->hotel_amount === (float) $this->hotel_balance
            && (float) $this->food_amount === (float) $this->food_balance;
    }

    public function isRefunded(): bool
    {
        return $this->refund_payment_date !== null;
    }
}
