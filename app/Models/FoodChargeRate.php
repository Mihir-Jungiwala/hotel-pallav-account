<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The monthly amount Pallav Food charges Hotel Pallav per employee, from a given month. */
class FoodChargeRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['monthly_amount' => 'decimal:2', 'effective_from' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }
}
