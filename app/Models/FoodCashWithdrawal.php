<?php

namespace App\Models;

use App\Models\Concerns\HasEntryNumber;
use App\Models\Concerns\LocksEntryStamp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodCashWithdrawal extends Model
{
    use HasEntryNumber, LocksEntryStamp;

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
