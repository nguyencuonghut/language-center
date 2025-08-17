<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassroomController extends Controller
{
    /**
     * Danh sách lớp học (giống phong cách Rooms/Index).
     * Hỗ trợ filter: ?branch=all|<id>, ?q=, ?per_page=, ?sort=, ?order=asc|desc
     */
    public function index(Request $request)
    {
        $branchParam = $request->query('branch'); // 'all' | null | <id>
        $q           = trim((string) $request->query('q', ''));
        $perPage     = (int) $request->query('per_page', 12);
        $sort        = (string) $request->query('sort', '');
        $order       = strtolower((string) $request->query('order', '')) === 'asc' ? 'asc' : 'desc';

        // Map các field cho phép sort → cột DB thực
        $sortableMap = [
            'code'           => 'classrooms.code',
            'name'           => 'classrooms.name',
            'start_date'     => 'classrooms.start_date',
            'sessions_total' => 'classrooms.sessions_total',
            'status'         => 'classrooms.status',
            'branch'         => 'branches.name',
            'course'         => 'courses.name',
            'teacher'        => 'users.name',
        ];
        $sortCol = $sortableMap[$sort] ?? null;

        $query = Classroom::query()
            ->leftJoin('branches', 'branches.id', '=', 'classrooms.branch_id')
            ->leftJoin('courses',  'courses.id',  '=', 'classrooms.course_id')
            ->leftJoin('users',    'users.id',    '=', 'classrooms.teacher_id')
            ->when($branchParam && $branchParam !== 'all', fn($qB) => $qB->where('classrooms.branch_id', (int) $branchParam))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($sub) use ($q) {
                    $sub->where('classrooms.code', 'like', "%{$q}%")
                        ->orWhere('classrooms.name', 'like', "%{$q}%");
                });
            });

        if ($sortCol) {
            $query->orderBy($sortCol, $order);
        } else {
            $query->orderBy('classrooms.branch_id')->orderBy('classrooms.code');
        }

        $classrooms = $query
            ->select([
                'classrooms.id',
                'classrooms.code',
                'classrooms.name',
                'classrooms.start_date',
                'classrooms.sessions_total',
                'classrooms.status',
                'branches.id as branch_id',
                'branches.name as branch_name',
                'courses.id as course_id',
                'courses.name as course_name',
                'users.id as teacher_id',
                'users.name as teacher_name',
            ])
            ->paginate(max(1, $perPage))
            ->withQueryString();

        $branches = Branch::select('id','name')->orderBy('name')->get();

        return Inertia::render('Admin/Classrooms/Index', [
            'classrooms' => $classrooms,
            'branches'   => $branches,
            'filters'    => [
                'branch'  => $branchParam ?: 'all',
                'q'       => $q,
                'perPage' => $perPage,
                'sort'    => $sort,
                'order'   => $sort ? $order : null,
            ],
        ]);
    }

    // Các action create/store/edit/update/destroy sẽ làm ở bước sau
}
