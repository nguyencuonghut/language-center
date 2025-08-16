<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'term_code',
        'course_id',
        'branch_id',
        'teacher_id',
        'start_date',
        'sessions_total',
        'tuition_fee',
        'status',
    ];
}
