<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEntry extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['overtime_hours' => 'decimal:2', 'skips_food' => 'boolean'];

    public function month(): BelongsTo
    {
        return $this->belongsTo(AttendanceMonth::class, 'attendance_month_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class, 'attendance_status_id');
    }
}
