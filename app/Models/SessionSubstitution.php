<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionSubstitution extends Model
{
    protected $fillable = [
        'class_session_id',
        'substitute_teacher_id',
        'rate_override',
        'reason',
        'approved_by',
        'approved_at',
    ];

    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function substituteTeacher()
    {
        return $this->belongsTo(User::class, 'substitute_teacher_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
