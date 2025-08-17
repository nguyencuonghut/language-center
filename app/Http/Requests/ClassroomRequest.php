<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // (sẽ siết bằng Policy/Middleware ở bước sau)
    }

    public function rules(): array
    {
        $classroom = $this->route('classroom'); // Model binding khi update
        $id = $classroom?->id;

        return [
            'code'           => [
                'required','string','max:50',
                Rule::unique('classrooms','code')->ignore($id),
            ],
            'name'           => ['required','string','max:150'],
            'term_code'      => ['nullable','string','max:20'],
            'course_id'      => ['required','exists:courses,id'],
            'branch_id'      => ['required','exists:branches,id'],
            'teacher_id'     => ['nullable','exists:users,id'], // lọc “chỉ teacher” ở Controller (danh sách options)
            'start_date'     => ['required','date'],
            'sessions_total' => ['required','integer','min:1','max:500'],
            'tuition_fee'    => ['required','integer','min:0'],
            'status'         => ['required', Rule::in(['open','closed'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'code'           => 'mã lớp',
            'name'           => 'tên lớp',
            'term_code'      => 'học kỳ',
            'course_id'      => 'khóa học',
            'branch_id'      => 'chi nhánh',
            'teacher_id'     => 'giáo viên',
            'start_date'     => 'ngày bắt đầu',
            'sessions_total' => 'số buổi',
            'tuition_fee'    => 'học phí',
            'status'         => 'trạng thái',
        ];
    }
}
