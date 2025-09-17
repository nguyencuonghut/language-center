<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\Teacher;
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
        // Ánh xạ từ tiếng Việt sang enum value cho education_level
        $educationMapping = [
            'cử nhân' => 'bachelor',
            'kỹ sư'   => 'engineer',
            'thạc sĩ' => 'master',
            'tiến sĩ' => 'phd',
            'khác'    => 'other',
        ];

        $teachers = Teacher::when($request->search, function ($query, $search) use ($educationMapping) {
                $query->where('full_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      // Thêm search cho education_level bằng tiếng Việt (case-insensitive)
                      ->orWhere('education_level', $educationMapping[strtolower(trim($search))] ?? null);
            })
            ->select('id', 'full_name', 'email', 'phone', 'status', 'education_level', 'created_at')
            ->orderBy('full_name')
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

        // Tạo User cho đăng nhập
        $user = User::create([
            'name'     => $data['full_name'],  // Sử dụng full_name từ request
            'email'    => $data['email'] ?? null,
            'phone'    => $data['phone'] ?? null,
            'password' => $data['password'],  // Đã được hash trong StoreTeacherRequest
            'active'   => $data['active'] ?? true,
        ]);

        // Gán role teacher
        $user->assignRole('teacher');

        // Tạo Teacher liên kết với User
        Teacher::create([
            'user_id'         => $user->id,
            'code'            => $data['code'] ?? 'T' . str_pad($user->id, 4, '0', STR_PAD_LEFT),  // Tạo code nếu không có
            'full_name'       => $data['full_name'],
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'] ?? null,
            'address'         => $data['address'] ?? null,
            'national_id'     => $data['national_id'] ?? null,
            'photo_path'      => $data['photo_path'] ?? null,
            'education_level' => $data['education_level'] ?? null,
            'status'          => $data['status'] ?? 'active',
            'notes'           => $data['notes'] ?? null,
        ]);

        return redirect()->route('manager.teachers.index')
            ->with('success', 'Đã thêm giáo viên thành công.');
    }

    public function show(Teacher $teacher)
    {
        // Load user liên kết để lấy thông tin đăng nhập
        $teacher->load('user.roles');

        // Lấy assignments + class liên quan (teacher_id giờ là teachers.id)
        $assignments = TeachingAssignment::with(['classroom' => function ($q) {
                $q->select('id', 'code', 'name');
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

        return Inertia::render('Manager/Teachers/Show', [
            'teacher'     => [
                'id'              => $teacher->id,
                'user_id'         => $teacher->user_id,
                'code'            => $teacher->code,
                'full_name'       => $teacher->full_name,
                'email'           => $teacher->email,
                'phone'           => $teacher->phone,
                'address'         => $teacher->address,
                'national_id'     => $teacher->national_id,
                'photo_path'      => $teacher->photo_path,
                'education_level' => $teacher->education_level,
                'status'          => $teacher->status,
                'notes'           => $teacher->notes,
                'created_at'      => $teacher->created_at?->toDateString(),
                'updated_at'      => $teacher->updated_at?->toDateString(),
                'user'            => $teacher->user ? [
                    'active'        => $teacher->user->active,
                    'roles'         => $teacher->user->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
                    'role_names_vi' => $teacher->user->role_names_vi,  // Accessor tiếng Việt nếu có
                ] : null,
            ],
            'assignments' => $assignments,
        ]);
    }

    /**
     * Form chỉnh sửa
     */
    public function edit(Teacher $teacher)
    {
        $teacher->load('user');

        return Inertia::render('Manager/Teachers/Edit', [
            'teacher' => [
                'id'              => $teacher->id,
                'user_id'         => $teacher->user_id,
                'code'            => $teacher->code,
                'full_name'       => $teacher->full_name,
                'email'           => $teacher->email,
                'phone'           => $teacher->phone,
                'address'         => $teacher->address,
                'national_id'     => $teacher->national_id,
                'photo_path'      => $teacher->photo_path,
                'education_level' => $teacher->education_level,
                'status'          => $teacher->status,
                'notes'           => $teacher->notes,
                'user'            => $teacher->user ? [
                    'active' => $teacher->user->active,
                ] : null,
            ]
        ]);
    }

    /**
     * Cập nhật giáo viên
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher)
    {
        $data = $request->validated();

        // Cập nhật User liên kết
        if ($teacher->user) {
            $userUpdateData = [
                'name'   => $data['full_name'],
                'email'  => $data['email'] ?? null,
                'phone'  => $data['phone'] ?? null,
                'active' => $data['active'] ?? true,
            ];
            if (isset($data['password'])) {
                $userUpdateData['password'] = $data['password'];
            }
            $teacher->user->update($userUpdateData);
        }

        // Cập nhật Teacher
        $teacher->update([
            'code'            => $data['code'] ?? $teacher->code,
            'full_name'       => $data['full_name'],
            'phone'           => $data['phone'] ?? null,
            'email'           => $data['email'] ?? null,
            'address'         => $data['address'] ?? null,
            'national_id'     => $data['national_id'] ?? null,
            'photo_path'      => $data['photo_path'] ?? null,
            'education_level' => $data['education_level'] ?? null,
            'status'          => $data['status'] ?? $teacher->status,
            'notes'           => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Đã cập nhật giáo viên thành công.');
    }

    /**
     * Xoá giáo viên
     */
    public function destroy(Teacher $teacher)
    {
        // Kiểm tra xem Teacher có relationship active không
        $hasRelationships = $teacher->assignments()->exists() || $teacher->timesheets()->exists();

        if ($hasRelationships) {
            // Nếu có relationship, chuyển status về 'terminated' và deactive User
            $teacher->update(['status' => 'terminated']);

            // Kiểm tra sau update
            $teacher->refresh();

            if ($teacher->user) {
                $teacher->user->update(['active' => false]);
            }
            $message = 'Đã chuyển giáo viên về trạng thái đã chấm dứt và vô hiệu hóa tài khoản.';
        } else {
            // Nếu không có relationship, xóa Teacher và User
            $teacher->delete();
            if ($teacher->user) {
                $teacher->user->delete();
            }
            $message = 'Đã xóa giáo viên thành công.';
        }

        return back()->with('success', $message);
    }
}
