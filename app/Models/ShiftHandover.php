<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftHandover extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public const DENOMINATIONS = [
        'd500' => 500, 'd200' => 200, 'd100' => 100, 'd50' => 50,
        'd20' => 20, 'd10' => 10, 'd5' => 5, 'coins' => 1,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }
}
