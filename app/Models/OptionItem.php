<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OptionItem extends Model
{
    protected $fillable = ['option_set_id', 'label', 'value', 'color', 'is_active', 'is_default', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function set(): BelongsTo
    {
        return $this->belongsTo(OptionSet::class, 'option_set_id');
    }
}
