<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TeacherTimesheet extends Model
{
    protected $fillable = [
        'class_session_id',
        'teacher_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function session()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class);
    }

    public function getApprovedAtAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : null;
    }

    public function getApprovedByAttribute($value)
    {
        return $value ? User::find($value)->name : null;
    }

}
