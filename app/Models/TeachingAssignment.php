<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeachingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        // Thêm các trường phù hợp nếu có, ví dụ:
        // 'teacher_id', 'class_id', 'assigned_at', ...
    ];
}
