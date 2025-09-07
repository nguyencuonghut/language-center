<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeachingAssignments\StoreTeachingAssignmentRequest;
use App\Http\Requests\TeachingAssignments\UpdateTeachingAssignmentRequest;
use App\Models\Classroom;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeachingAssignmentController extends Controller
{
    /**
     * Danh sách phân công theo lớp
     */
    public function index(Request $request, Classroom $classroom)
    {
        // Filter cơ bản (tùy chọn)
        $teacherId = $request->integer('teacher');

        $query = TeachingAssignment::query()
            ->with(['teacher:id,name,email'])
            ->where('class_id', $classroom->id)
            ->when($teacherId, fn($q) => $q->where('teacher_id', $teacherId))
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        $assignments = $query->paginate($request->integer('per_page', 12))->withQueryString();

        // Danh sách GV để chọn
        $teachers = User::role('teacher')
            ->select('id','name','email')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'label' => "{$u->name} ({$u->email})",
                'value' => (string)$u->id,
            ]);

        return Inertia::render('Manager/Classrooms/Assignments/Index', [
            'classroom'   => $classroom->only(['id','code','name','branch_id']),
            'assignments' => $assignments,
            'teachers'    => $teachers,
            'filters'     => [
                'teacher' => $teacherId ?: null,
                'perPage' => $request->integer('per_page', 12),
            ],
        ]);
    }

    /**
     * Form tạo mới
     */
    public function create(Request $request, Classroom $classroom)
    {
        $teachers = User::role('teacher')
            ->select('id','name','email')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'label' => "{$u->name} ({$u->email})",
                'value' => (string)$u->id,
            ]);

        // Gợi ý ngày hiệu lực: hôm nay
        $defaults = [
            'effective_from' => now()->toDateString(),
        ];

        return Inertia::render('Manager/TeachingAssignments/Create', [
            'classroom' => $classroom->only(['id','code','name','branch_id']),
            'teachers'  => $teachers,
            'defaults'  => $defaults,
        ]);
    }

    /**
     * LƯU MỚI (stub) — sẽ thay bằng FormRequest ở bước kế
     */
    public function store(StoreTeachingAssignmentRequest $request, Classroom $classroom)
    {
        $data = $request->validated();
        // đảm bảo class_id khớp route
        $data['class_id'] = $classroom->id;

        TeachingAssignment::create($data);

        return back()->with('success', 'Đã tạo phân công giáo viên.');
    }

    /**
     * Form sửa
     */
    public function edit(Request $request, Classroom $classroom, TeachingAssignment $assignment)
    {
        // Đảm bảo assignment thuộc lớp
        if ($assignment->class_id !== $classroom->id) {
            abort(404);
        }

        $teachers = User::role('teacher')
            ->select('id','name','email')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'label' => "{$u->name} ({$u->email})",
                'value' => (string)$u->id,
            ]);

        return Inertia::render('Manager/TeachingAssignments/Edit', [
            'classroom'  => $classroom->only(['id','code','name','branch_id']),
            'assignment' => [
                'id'               => $assignment->id,
                'teacher_id'       => $assignment->teacher_id,
                'rate_per_session' => (int)$assignment->rate_per_session,
                'effective_from'   => $assignment->effective_from ? $assignment->effective_from->toDateString() : '',
                'effective_to'     => optional($assignment->effective_to)->toDateString(),
            ],
            'teachers'   => $teachers,
        ]);
    }

    /**
     * CẬP NHẬT (stub) — sẽ thay bằng FormRequest ở bước kế
     */
    public function update(UpdateTeachingAssignmentRequest $request, Classroom $classroom, TeachingAssignment $assignment)
    {
        \Illuminate\Support\Facades\Log::info($request->all());
        // đảm bảo assignment thuộc classroom
        if ($assignment->class_id !== $classroom->id) {
            abort(404);
        }

        $data = $request->validated();
        $data['class_id'] = $classroom->id;

        $assignment->update($data);

        return back()->with('success', 'Đã cập nhật phân công.');
    }

    /**
     * XOÁ (stub)
     */
    public function destroy(Request $request, Classroom $classroom, TeachingAssignment $assignment)
    {
        if ($assignment->class_id !== $classroom->id) {
            abort(404);
        }

        $assignment->delete();

        return back()->with('success', 'Đã xoá phân công.');
    }
}
