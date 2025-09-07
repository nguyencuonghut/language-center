<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;

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
            'teacher'        => 'teachers.name',

        ];
        $sortCol = $sortableMap[$sort] ?? null;

        $query = Classroom::query()
            ->leftJoin('branches', 'branches.id', '=', 'classrooms.branch_id')
            ->leftJoin('courses',  'courses.id',  '=', 'classrooms.course_id')
            // Join với TeachingAssignment để lấy giáo viên hiện tại
            ->leftJoin('teaching_assignments', function($join) {
                $join->on('classrooms.id', '=', 'teaching_assignments.class_id')
                    ->where(function($q) {
                        $q->where('teaching_assignments.effective_from', '<=', now());
                    })
                    ->where(function($q) {
                        $q->whereNull('teaching_assignments.effective_to')
                            ->orWhere('teaching_assignments.effective_to', '>=', now());
                    });
            })
            ->leftJoin('users as teachers', 'teachers.id', '=', 'teaching_assignments.teacher_id')
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
                'teachers.id as teacher_id',
                'teachers.name as teacher_name',
                'teaching_assignments.rate_per_session',
            ])
            ->paginate(max(1, $perPage))
            ->withQueryString();

        $branches = Branch::select('id','name')->orderBy('name')->get();

        return Inertia::render('Manager/Classrooms/Index', [
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

        // gợi ý branch từ query ?branch=
        $suggestBranch = $request->query('branch');
        $suggestBranchId = ($suggestBranch && $suggestBranch !== 'all') ? (int) $suggestBranch : null;

        return Inertia::render('Manager/Classrooms/Create', [
            'branches'        => $branches,
            'courses'         => $courses,
            'suggestBranchId' => $suggestBranchId,
        ]);
    }

    // ========== STORE ==========
    public function store(ClassroomRequest $request)
    {
        $data = $request->validated();
        $cls = Classroom::create($data);

        return redirect()
            ->route('manager.classrooms.index', $request->only('branch')) // giữ query nếu có
            ->with('success', 'Tạo lớp thành công.');
    }

    // ========== EDIT ==========
    public function edit(Classroom $classroom)
    {
        $branches = Branch::select('id','name')->orderBy('name')->get();
        $courses  = Course::select('id','name')->orderBy('name')->get();

        return Inertia::render('Manager/Classrooms/Edit', [
            'classroom' => $classroom->only([
                'id','code','name','term_code','course_id','branch_id',
                'start_date','sessions_total','tuition_fee','status'
            ]),
            'branches'  => $branches,
            'courses'   => $courses,
        ]);
    }

    // ========== UPDATE ==========
    public function update(ClassroomRequest $request, Classroom $classroom)
    {
        $data = $request->validated();
        $classroom->update($data);

        return redirect()
            ->route('manager.classrooms.index', $request->only('branch'))
            ->with('success', 'Cập nhật lớp thành công.');
    }

    // ========== DESTROY ==========
    public function destroy(Classroom $classroom)
    {
        $classroom->delete();

        return redirect()
            ->route('manager.classrooms.index', request()->only('branch'))
            ->with('success', 'Đã xoá lớp.');
    }

    // ========== SEARCH API ==========
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $availableForTransfer = $request->boolean('available_for_transfer', false);

        $query = Classroom::query()
            ->leftJoin('branches', 'branches.id', '=', 'classrooms.branch_id')
            ->leftJoin('courses', 'courses.id', '=', 'classrooms.course_id');

        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('classrooms.code', 'like', "%{$q}%")
                        ->orWhere('classrooms.name', 'like', "%{$q}%")
                        ->orWhere('courses.name', 'like', "%{$q}%");
            });
        }

        // If searching for transfer options, only show active classes
        if ($availableForTransfer) {
            $query->whereIn('classrooms.status', ['active', 'open']);
        }

        $classrooms = $query
            ->orderBy('classrooms.name')
            ->limit(50)
            ->get([
                'classrooms.id',
                'classrooms.code',
                'classrooms.name',
                'classrooms.status',
                'courses.name as course_name',
                'branches.name as branch_name'
            ])
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'status' => $c->status,
                    'course_name' => $c->course_name,
                    'branch_name' => $c->branch_name,
                ];
            });

        return response()->json($classrooms);
    }
}
