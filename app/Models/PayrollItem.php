<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_id',
        'teacher_timesheet_id',
        'teacher_id',
        'class_session_id',
        'amount',
        'note',
    ];

    // ---- Relations ----
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function timesheet(): BelongsTo
    {
        return $this->belongsTo(TeacherTimesheet::class, 'teacher_timesheet_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }
}
