<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teacherId = $this->route('teacher')?->id ?? null;

        return [
            'user_id' => ['nullable','exists:users,id'],
            'code' => ['sometimes','required','string','max:191', Rule::unique('teachers','code')->ignore($teacherId)],
            'full_name' => ['sometimes','required','string','max:191'],
            'phone' => ['nullable','string','max:50', Rule::unique('teachers','phone')->ignore($teacherId)],
            'email' => ['nullable','email','max:191', Rule::unique('teachers','email')->ignore($teacherId)],
            'address' => ['nullable','string'],
            'national_id' => ['nullable','string','max:100'],
            'education_level' => ['nullable', Rule::in(['bachelor','engineer','master','phd','other'])],
            'status' => ['required', Rule::in(['active','on_leave','terminated','adjunct','inactive'])],
            'notes' => ['nullable','string'],
            'photo' => ['nullable','file','image','max:2048'],
            'remove_photo' => ['nullable','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã giáo viên là bắt buộc.',
            'code.unique' => 'Mã giáo viên đã tồn tại.',
            'full_name.required' => 'Họ và tên giáo viên là bắt buộc.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'email.unique' => 'Email giáo viên đã tồn tại.',
            'email.email' => 'Email giáo viên không đúng định dạng.',
            'status.required' => 'Trạng thái giáo viên là bắt buộc.',
            'status.in' => 'Trạng thái giáo viên không hợp lệ.',
            'education_level.in' => 'Trình độ giáo viên không hợp lệ.',
            'photo.image' => 'Ảnh đại diện phải là file ảnh.',
            'photo.max' => 'Ảnh đại diện không được lớn hơn :max KB.',
            'remove_photo.boolean' => 'Giá trị xóa ảnh không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'Mã giáo viên',
            'full_name' => 'Họ và tên',
            'phone' => 'Số điện thoại',
            'email' => 'Email',
            'address' => 'Địa chỉ',
            'national_id' => 'Số CMND/CCCD',
            'education_level' => 'Trình độ',
            'status' => 'Trạng thái',
            'notes' => 'Ghi chú',
            'photo' => 'Ảnh đại diện',
            'remove_photo' => 'Xóa ảnh đại diện',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        // Hash password nếu có và không rỗng
        if (!empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            // Loại bỏ password khỏi data nếu không có giá trị
            unset($data['password']);
        }

        // Đổi tên key để tránh conflict (frontend có thể gửi 'email' cho user và 'teacher_email' cho teacher)
        if (isset($data['teacher_email'])) {
            $data['email'] = $data['teacher_email']; // Cho teacher
            unset($data['teacher_email']);
        }
        if (isset($data['teacher_phone'])) {
            $data['phone'] = $data['teacher_phone']; // Cho teacher
            unset($data['teacher_phone']);
        }

        return $data;
    }
}
