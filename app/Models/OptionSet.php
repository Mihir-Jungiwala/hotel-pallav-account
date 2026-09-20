<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionSet extends Model
{
    public const INPUTS = ['select' => 'Dropdown', 'radio' => 'Option cards', 'checkbox' => 'Checkboxes'];

    /**
     * Lists of people. All that is kept for each is the name: no separate
     * saved value, colour, default or hidden state, and they read alphabetically.
     */
    public const NAME_ONLY = ['revenue_depositor_hotel', 'revenue_depositor_food', 'cash_person'];

    protected $fillable = ['key', 'name', 'description', 'icon', 'input', 'is_system', 'sort_order'];

    protected $casts = [
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OptionItem::class)->orderBy('sort_order')->orderBy('label');
    }

    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    public function isNameOnly(): bool
    {
        return in_array($this->key, self::NAME_ONLY, true);
    }

    public function inputLabel(): string
    {
        return self::INPUTS[$this->input] ?? 'Dropdown';
    }
}
