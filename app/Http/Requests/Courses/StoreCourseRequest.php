<?php

namespace App\Http\Requests\Courses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'code'     => ['required', 'string', 'max:50', 'unique:courses,code'],
            'name'     => ['required', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:50'],
            'active'   => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code'     => 'mã khóa học',
            'name'     => 'tên khóa học',
            'audience' => 'đối tượng',
            'language' => 'ngôn ngữ',
            'active'   => 'trạng thái hoạt động',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute',
            'string'   => ':Attribute phải là chuỗi',
            'max'      => ':Attribute không được vượt quá :max ký tự',
            'unique'   => ':Attribute đã tồn tại',
            'boolean'  => ':Attribute không hợp lệ',
        ];
    }
}
