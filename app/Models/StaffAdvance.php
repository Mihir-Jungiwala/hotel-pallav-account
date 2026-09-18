<?php

namespace App\Models;

use App\Models\Concerns\HasEntryNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAdvance extends Model
{
    use HasEntryNumber;

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
