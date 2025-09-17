<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTeacherWithUserRequest;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class TeacherWizardController extends Controller
{
    public function create(Request $request)
    {
        // Nếu dùng Inertia, trả về page; nếu thuần API, có thể bỏ
        return inertia('Manager/Teachers/WizardCreate', [
            'educationLevels' => ['bachelor','engineer','master','phd','other'],
            'teacherStatuses' => ['active','on_leave','terminated','adjunct','inactive'],
            'defaults' => [
                'user' => ['active' => true],
                'teacher' => ['status' => 'active', 'education_level' => null],
            ],
        ]);
    }

    public function store(StoreTeacherWithUserRequest $request)
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data, $request) {
            // 1) Tạo User + role=teacher
            /** @var \App\Models\User $user */
            $user = User::create([
                'name' => $data['user']['name'],
                'email' => $data['user']['email'],
                'phone' => $data['teacher']['phone'], // Sử dụng số điện thoại của Teacher làm phone của User
                'password' => Hash::make($data['user']['password']),
                'active' => $data['user']['active'],
            ]);

            // Gán role=teacher (spatie/permission)
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('teacher');
            }

            // 2) Xử lý upload ảnh nếu có
            $photoPath = null;
            if (request()->hasFile('teacher.photo')) {
                $photoPath = request()->file('teacher.photo')->store('private/teachers', 'local');
            }

            // 3) Tạo Teacher (prefill email nếu chưa cung cấp)
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'code' => $data['teacher']['code'],
                'full_name' => $data['teacher']['full_name'],
                'phone' => $data['teacher']['phone'] ?? null,
                'email' => $data['teacher']['email'] ?? $user->email,
                'address' => $data['teacher']['address'] ?? null,
                'national_id' => $data['teacher']['national_id'] ?? null,
                'photo_path' => $photoPath,
                'education_level' => $data['teacher']['education_level'] ?? null,
                'status' => $data['teacher']['status'],
                'notes' => $data['teacher']['notes'] ?? null,
            ]);

            return [$user, $teacher];
        });

        [$user, $teacher] = $result;

        // Trả về JSON nếu API; hoặc redirect nếu Inertia
        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Tạo User + Teacher thành công',
                'user' => $user,
                'teacher' => $teacher,
            ], 201);
        }

        return redirect()->route('manager.teachers.index')
            ->with('success', 'Tạo User + Teacher thành công');
    }
}
