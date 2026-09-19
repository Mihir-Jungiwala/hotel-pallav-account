<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of the payroll log. Written once, never edited. */
class PayrollLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    /** Shown as the pill on each row. */
    public const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'emailed' => 'Emailed',
        'error' => 'Error',
    ];
}
