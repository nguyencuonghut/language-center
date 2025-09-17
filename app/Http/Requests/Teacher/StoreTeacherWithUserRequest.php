<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherWithUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Middleware đã check role admin/manager, ở đây cứ cho phép
        return true;
    }

    public function rules(): array
    {
        return [
            // --- Tab 1: Thông tin User (login) ---
            'user.name' => ['required','string','max:191'],
            'user.email' => ['required','email','max:191','unique:users,email'],
            'user.password' => ['required','string','min:8','max:191','confirmed'],
            'user.active' => ['required','boolean'],

            // --- Tab 2: Hồ sơ Teacher ---
            'teacher.code' => ['required','string','max:191','unique:teachers,code'],
            'teacher.name' => ['required','string','max:191'],
            'teacher.phone' => ['nullable','string','max:50','unique:teachers,phone'],
            'teacher.email' => ['nullable','email','max:191','unique:teachers,email'],
            'teacher.address' => ['nullable','string'],
            'teacher.national_id' => ['nullable','string','max:100'],
            'teacher.education_level' => ['nullable', Rule::in(['bachelor','engineer','master','phd','other'])],
            'teacher.status' => ['required', Rule::in(['active','on_leave','terminated','adjunct','inactive'])],
            'teacher.notes' => ['nullable','string'],
            'teacher.photo' => ['nullable','file','image','max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'user.name.required' => 'Tên đăng nhập là bắt buộc.',
            'user.email.required' => 'Email đăng nhập là bắt buộc.',
            'user.email.email' => 'Email đăng nhập không đúng định dạng.',
            'user.email.unique' => 'Email đăng nhập đã tồn tại.',
            'user.password.required' => 'Mật khẩu là bắt buộc.',
            'user.password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
            'user.password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'user.active.required' => 'Trạng thái kích hoạt là bắt buộc.',
            'teacher.code.required' => 'Mã giáo viên là bắt buộc.',
            'teacher.code.unique' => 'Mã giáo viên đã tồn tại.',
            'teacher.name.required' => 'Họ và tên giáo viên là bắt buộc.',
            'teacher.phone.unique' => 'Số điện thoại đã tồn tại.',
            'teacher.email.unique' => 'Email giáo viên đã tồn tại.',
            'teacher.email.email' => 'Email giáo viên không đúng định dạng.',
            'teacher.status.required' => 'Trạng thái giáo viên là bắt buộc.',
            'teacher.status.in' => 'Trạng thái giáo viên không hợp lệ.',
            'teacher.education_level.in' => 'Trình độ giáo viên không hợp lệ.',
            'teacher.photo.image' => 'Ảnh đại diện phải là file ảnh.',
            'teacher.photo.max' => 'Ảnh đại diện không được lớn hơn :max KB.',
        ];
    }
}
