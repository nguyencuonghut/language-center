<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check(); // (sẽ siết bằng Policy/Middleware ở bước sau)
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
            'start_date'     => 'ngày bắt đầu',
            'sessions_total' => 'số buổi',
            'tuition_fee'    => 'học phí',
            'status'         => 'trạng thái',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'           => 'Mã lớp là bắt buộc.',
            'code.max'              => 'Mã lớp không được vượt quá 50 ký tự.',
            'code.unique'           => 'Mã lớp đã tồn tại trong hệ thống.',
            'name.required'           => 'Tên lớp là bắt buộc.',
            'name.max'              => 'Tên lớp không được vượt quá 150 ký tự.',
            'term_code.max'           => 'Học kỳ không được vượt quá 20 ký tự.',
            'course_id.required'      => 'Khóa học là bắt buộc.',
            'course_id.exists'        => 'Khóa học không tồn tại trong hệ thống.',
            'branch_id.required'      => 'Chi nhánh là bắt buộc.',
            'branch_id.exists'        => 'Chi nhánh không tồn tại trong hệ thống.',
            'start_date.required'     => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date'       => 'Ngày bắt đầu phải là một ngày hợp lệ.',
            'sessions_total.required' => 'Số buổi là bắt buộc.',
            'sessions_total.integer'  => 'Số buổi phải là một số nguyên.',
            'sessions_total.min'      => 'Số buổi phải lớn hơn hoặc bằng 1.',
            'sessions_total.max'      => 'Số buổi không được vượt quá 500.',
            'tuition_fee.required'    => 'Học phí là bắt buộc.',
            'tuition_fee.integer'     => 'Học phí phải là một số nguyên.',
            'tuition_fee.min'       => 'Học phí phải lớn hơn hoặc bằng 0.',
            'status.required'         => 'Trạng thái là bắt buộc.',
            'status.in'             => 'Trạng thái phải là "mở" hoặc "đóng".',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('start_date')) {
            $startDate = $this->get('start_date');

            // Handle ISO 8601 format with timezone
            if (is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z?$/', $startDate)) {
                $this->merge([
                    'start_date' => \Carbon\Carbon::parse($startDate)->format('Y-m-d H:i:s')
                ]);
            }
        }
    }
}
