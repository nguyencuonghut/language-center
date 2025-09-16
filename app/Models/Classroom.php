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

    /**
     * Scope: Lấy các lớp mà giáo viên này được phân công (dạy chính hoặc dạy thay),
     * có xét hiệu lực của teaching_assignments (effective_from, effective_to).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $teacherId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTeacher($query, int $teacherId)
    {
        return $query
            ->where(function ($q) use ($teacherId) {
                // Dạy chính (teaching_assignments)
                $q->whereHas('teachingAssignments', function ($q2) use ($teacherId) {
                    $q2->where('teacher_id', $teacherId);
                })
                // Hoặc dạy thay (class_sessions.substitutions)
                ->orWhereHas('sessions.substitution', function ($q3) use ($teacherId) {
                    $q3->where('substitute_teacher_id', $teacherId);
                });
            });

        // $now = now();

        // return $query->where(function ($q) use ($teacherId, $now) {
        //     $q->whereHas('teachingAssignments', function ($q2) use ($teacherId, $now) {
        //         $q2->where('teacher_id', $teacherId)
        //             ->where(function ($q3) use ($now) {
        //                 $q3->whereNull('effective_from')
        //                     ->orWhere('effective_from', '<=', $now);
        //             })
        //             ->where(function ($q3) use ($now) {
        //                 $q3->whereNull('effective_to')
        //                     ->orWhere('effective_to', '>=', $now);
        //             });
        //     })
        //     ->orWhereHas('sessions.substitution', function ($q4) use ($teacherId) {
        //         $q4->where('substitute_teacher_id', $teacherId);
        //     });
        // });
    }
}
