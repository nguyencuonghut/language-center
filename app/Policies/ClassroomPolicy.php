<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Classroom;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClassroomPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine whether the user can view the classroom.
     * - Admin/Manager: always true (handled by middleware)
     * - Teacher: chỉ xem được lớp mình được phân công (dạy chính còn hiệu lực hoặc từng dạy thay)
     */
    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('manager')) {
            return true;
        }

        if ($user->hasRole('teacher')) {
            // Dạy chính: có teaching_assignment với teacher_id
            $isAssigned = $classroom->teachingAssignments()
                ->where('teacher_id', $user->id)
                ->exists();

            // Hoặc dạy thay: có session substitution với teacher_id
            $isSubstituted = $classroom->sessions()
                ->whereHas('substitution', function ($q) use ($user) {
                    $q->where('substitute_teacher_id', $user->id);
                })
                ->exists();

            return $isAssigned || $isSubstituted;
        }

        return false;
    }

    /**
     * Các quyền khác (create, update, delete) chỉ dành cho admin/manager.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->hasRole('admin') || $user->hasRole('manager');
    }
}
