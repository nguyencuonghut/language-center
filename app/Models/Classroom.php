<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'term_code', 'course_id', 'branch_id', 'start_date', 'sessions_total', 'tuition_fee', 'status'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    public function sessions()
    {
        return $this->hasMany(ClassSession::class, 'class_id');
    }

    /**
     * Relationship với tất cả teaching assignments của lớp
     */
    public function teachingAssignments()
    {
        return $this->hasMany(TeachingAssignment::class, 'class_id');
    }

    /**
     * Relationship chỉ lấy teaching assignment hiện hành (có hiệu lực và chưa kết thúc)
     */
    public function currentTeachingAssignment()
    {
        return $this->hasOne(TeachingAssignment::class, 'class_id')
            ->whereNull('effective_to')
            ->where(function($q) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', now());
            });
    }

    /**
     * Relationship với giáo viên hiện hành thông qua currentTeachingAssignment
     */
    public function currentTeacher()
    {
        return $this->belongsTo(User::class, 'teacher_id')
            ->through('currentTeachingAssignment', 'teacher');
    }

    /**
     * Lấy giáo viên hiện hành và thông tin phân công
     * @return array|null ['teacher' => User, 'assignment' => TeachingAssignment]
     */
    public function getCurrentTeacherInfo()
    {
        $assignment = $this->currentTeachingAssignment()->with('teacher')->first();
        if (!$assignment) {
            return null;
        }

        return [
            'teacher' => $assignment->teacher,
            'assignment' => $assignment
        ];
    }

    /**
     * Local scope để join với giáo viên hiện hành
     */
    public function scopeWithCurrentTeacher($query)
    {
        return $query->addSelect([
            'current_teacher_id' => TeachingAssignment::select('teacher_id')
                ->whereColumn('class_id', 'classrooms.id')
                ->whereNull('effective_to')
                ->where(function($q) {
                    $q->whereNull('effective_from')
                      ->orWhere('effective_from', '<=', now());
                })
                ->limit(1)
        ])->with(['currentTeachingAssignment.teacher']);
    }
}
