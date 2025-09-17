<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'full_name',
        'phone',
        'email',
        'address',
        'national_id',
        'photo_path',
        'education_level',
        'status',
        'notes',
    ];

    protected $casts = [
        'education_level' => 'string',  // Hoặc enum nếu cần
        'status' => 'string',  // Đảm bảo cast đúng
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    public function certificates(): BelongsToMany {
        return $this->belongsToMany(Certificate::class)
            ->withTimestamps()
            ->withPivot(['credential_no','issued_by','issued_at','expires_at','file_path']);
    }

    public function assignments(): HasMany {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function timesheets(): HasMany {
        return $this->hasMany(TeacherTimesheet::class);
    }
}
