<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\User;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeacherController extends Controller
{
    /**
     * Danh sách giáo viên
     */
    public function index(Request $request)
    {
        $teachers = User::role('teacher')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
            })
            ->select('id', 'name', 'email', 'phone', 'active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Manager/Teachers/Index', [
            'teachers' => $teachers,
            'filters'  => $request->only('search')
        ]);
    }

    /**
     * Form tạo giáo viên
     */
    public function create()
    {
        return Inertia::render('Manager/Teachers/Create');
    }

    /**
     * Lưu giáo viên mới
     */
    public function store(StoreTeacherRequest $request)
    {
        $data = $request->validated();

        $teacher = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'] ?? null,
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'], // Đã được hash trong StoreTeacherRequest
            'active'   => $data['active'] ?? true, // Default active
        ]);

        // Gán role teacher
        $teacher->assignRole('teacher');

        return redirect()->route('manager.teachers.index')
            ->with('success', 'Đã thêm giáo viên thành công.');
    }

    public function show(User $teacher)
    {
        // Đảm bảo $teacher là user có role 'teacher'
        // (tạm thời không dùng Policy, nhưng bạn có thể validate thêm nếu cần)
        if (!$teacher->hasRole('teacher')) {
            abort(404);
        }

        // Lấy assignments + class liên quan
        $assignments = TeachingAssignment::with(['classroom' => function ($q) {
                $q->select('id','code','name');
            }])
            ->where('teacher_id', $teacher->id)
            ->orderBy('effective_from', 'desc')
            ->get()
            ->map(function ($a) {
                return [
                    'id'               => $a->id,
                    'rate_per_session' => (int) $a->rate_per_session,
                    'effective_from'   => $a->effective_from,
                    'effective_to'     => $a->effective_to,
                    'classroom'        => $a->classroom ? [
                        'id'   => $a->classroom->id,
                        'code' => $a->classroom->code,
                        'name' => $a->classroom->name,
                    ] : null,
                ];
            });

        // Chuẩn hoá roles (nếu bạn đã eager load từ trước, bỏ phần này)
        $teacher->load('roles');

        return inertia('Manager/Teachers/Show', [
            'teacher'     => [
                'id'         => $teacher->id,
                'name'       => $teacher->name,
                'email'      => $teacher->email,
                'phone'      => $teacher->phone,
                'active'     => $teacher->active,
                'created_at' => $teacher->created_at?->toDateString(),
                'updated_at' => $teacher->updated_at?->toDateString(),
                'roles'      => $teacher->roles->map(fn($r) => ['id'=>$r->id, 'name'=>$r->name]),
            ],
            'assignments' => $assignments,
        ]);
    }

    /**
     * Form chỉnh sửa
     */
    public function edit(User $teacher)
    {
        abort_unless($teacher->hasRole('teacher'), 404);

        return Inertia::render('Manager/Teachers/Edit', [
            'teacher' => [
                'id'     => $teacher->id,
                'name'   => $teacher->name,
                'email'  => $teacher->email,
                'phone'  => $teacher->phone,
                'active' => $teacher->active,
            ]
        ]);
    }

    /**
     * Cập nhật giáo viên
     */
    public function update(UpdateTeacherRequest $request, User $teacher)
    {
        abort_unless($teacher->hasRole('teacher'), 404);

        $data = $request->validated();

        // Cập nhật thông tin cơ bản
        $updateData = [
            'name'   => $data['name'],
            'email'  => $data['email'] ?? null,
            'phone'  => $data['phone'] ?? null,
            'active' => $data['active'] ?? true,
        ];

        // Thêm password nếu có
        if (isset($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $teacher->update($updateData);

        return back()->with('success', 'Đã cập nhật giáo viên thành công.');
    }

    /**
     * Xoá giáo viên
     */
    public function destroy(User $teacher)
    {
        abort_unless($teacher->hasRole('teacher'), 404);

        $teacher->delete();

        return back()->with('success', 'Đã xoá giáo viên thành công.');
    }
}
