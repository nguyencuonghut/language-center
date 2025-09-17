<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        //
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
        return Inertia::render('Manager/Teachers/Edit', [
            'teacher' => $teacher->only([
                'id','user_id','code','full_name','phone','email','address',
                'national_id','photo_path','education_level','status','notes'
            ]),
            'educationLevels' => ['bachelor','engineer','master','phd','other'],
            'teacherStatuses' => ['active','on_leave','terminated','adjunct','inactive'],
        ]);
    }

    /**
     * Cập nhật giáo viên
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher)
    {
        $data = $request->validated();

        // Xử lý ảnh
        if (($data['remove_photo'] ?? false) && $teacher->photo_path) {
            Storage::delete($teacher->photo_path);
            $data['photo_path'] = null;
        }
        if ($request->hasFile('photo')) {
            if ($teacher->photo_path) Storage::delete($teacher->photo_path);
            $data['photo_path'] = $request->file('photo')->store('private/teachers','local');
        }

        unset($data['photo'], $data['remove_photo']);

        $teacher->update($data);

        return redirect()->route('manager.teachers.index', $teacher->id)
            ->with('success', 'Cập nhật hồ sơ giáo viên thành công.');
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
