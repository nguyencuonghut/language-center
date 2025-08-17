<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomRequest;
use App\Models\Classroom;
use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassroomController extends Controller
{
    // ========== INDEX giữ nguyên như bạn đang dùng ==========

    public function index(Request $request)
    {
        $branchParam = $request->query('branch'); // 'all' | null | <id>
        $q           = trim((string) $request->query('q', ''));
        $perPage     = (int) $request->query('per_page', 12);
        $sort        = (string) $request->query('sort', '');
        $order       = strtolower((string) $request->query('order', '')) === 'asc' ? 'asc' : 'desc';

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
                'classrooms.term_code',
                'classrooms.start_date',
                'classrooms.sessions_total',
                'classrooms.tuition_fee',
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

    // ========== CREATE ==========
    public function create(Request $request)
    {
        $branches = Branch::select('id','name')->orderBy('name')->get();
        $courses  = Course::select('id','name')->orderBy('name')->get();
        $teachers = User::role('teacher')->select('id','name')->orderBy('name')->get();

        // gợi ý branch từ query ?branch=
        $suggestBranch = $request->query('branch');
        $suggestBranchId = ($suggestBranch && $suggestBranch !== 'all') ? (int) $suggestBranch : null;

        return Inertia::render('Admin/Classrooms/Create', [
            'branches'        => $branches,
            'courses'         => $courses,
            'teachers'        => $teachers,
            'suggestBranchId' => $suggestBranchId,
        ]);
    }

    // ========== STORE ==========
    public function store(ClassroomRequest $request)
    {
        $data = $request->validated();
        $cls = Classroom::create($data);

        return redirect()
            ->route('admin.classrooms.index', $request->only('branch')) // giữ query nếu có
            ->with('success', 'Tạo lớp thành công.');
    }

    // ========== EDIT ==========
    public function edit(Classroom $classroom)
    {
        $branches = Branch::select('id','name')->orderBy('name')->get();
        $courses  = Course::select('id','name')->orderBy('name')->get();
        $teachers = User::role('teacher')->select('id','name')->orderBy('name')->get();

        return Inertia::render('Admin/Classrooms/Edit', [
            'classroom' => $classroom->only([
                'id','code','name','term_code','course_id','branch_id','teacher_id',
                'start_date','sessions_total','tuition_fee','status'
            ]),
            'branches'  => $branches,
            'courses'   => $courses,
            'teachers'  => $teachers,
        ]);
    }

    // ========== UPDATE ==========
    public function update(ClassroomRequest $request, Classroom $classroom)
    {
        $data = $request->validated();
        $classroom->update($data);

        return redirect()
            ->route('admin.classrooms.index', $request->only('branch'))
            ->with('success', 'Cập nhật lớp thành công.');
    }

    // ========== DESTROY ==========
    public function destroy(Classroom $classroom)
    {
        $classroom->delete();

        return redirect()
            ->route('admin.classrooms.index', request()->only('branch'))
            ->with('success', 'Đã xoá lớp.');
    }
}
