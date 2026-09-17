<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryProcessingLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
    ];

    public function processing(): BelongsTo
    {
        return $this->belongsTo(SalaryProcessing::class, 'salary_processing_id');
    }
}
