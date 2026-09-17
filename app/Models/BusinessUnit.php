<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessUnit extends Model
{
    public const HOTEL = 'hotel';

    public const FOOD = 'food';

    protected $fillable = ['name', 'slug', 'code', 'accent', 'icon', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shiftHandovers(): HasMany
    {
        return $this->hasMany(ShiftHandover::class);
    }

    public function staffAdvances(): HasMany
    {
        return $this->hasMany(StaffAdvance::class);
    }

    public function initials(): string
    {
        return $this->code;
    }
}
