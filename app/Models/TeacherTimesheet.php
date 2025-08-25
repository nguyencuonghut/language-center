<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherTimesheet extends Model
{
    protected $fillable = [
        'class_session_id',
        'teacher_id',
        'amount',
        'status',
    ];
}
