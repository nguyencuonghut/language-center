<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HolidayRequest extends FormRequest
{
    public function authorize()
    {
        // Có thể kiểm tra quyền ở đây nếu cần
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'scope' => ['required', Rule::in(['global','branch','class'])],
            'branch_id' => [
                'required_if:scope,branch',
                'nullable',
                'exists:branches,id'
            ],
            'class_id' => [
                'required_if:scope,class',
                'nullable',
                'exists:classrooms,id'
            ],
            'recurring_yearly' => ['boolean'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Vui lòng nhập tên ngày nghỉ.',
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu.',
            'end_date.required' => 'Vui lòng chọn ngày kết thúc.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'scope.required' => 'Vui lòng chọn phạm vi.',
            'scope.in' => 'Phạm vi không hợp lệ.',
            'branch_id.exists' => 'Chi nhánh không hợp lệ.',
            'branch_id.required_if' => 'Vui lòng chọn chi nhánh.',
            'class_id.exists' => 'Lớp học không hợp lệ.',
            'class_id.required_if' => 'Vui lòng chọn lớp học.',
        ];
    }
}
