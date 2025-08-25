<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tuỳ RBAC sau này
    }

    public function rules(): array
    {
        return [
            'student_id'       => ['required','integer','exists:students,id'],
            'enrolled_at'      => ['nullable','date'],
            'start_session_no' => ['nullable','integer','min:1'],
            'status'           => ['nullable', Rule::in(['active','transferred','completed','dropped'])],
            // KHÔNG có 'note' theo schema
        ];
    }

    public function attributes(): array
    {
        return [
            'student_id'       => 'học viên',
            'enrolled_at'      => 'ngày ghi danh',
            'start_session_no' => 'bắt đầu từ buổi số',
            'status'           => 'trạng thái',
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required' => 'Vui lòng chọn học viên.',
            'student_id.exists'   => 'Học viên không hợp lệ.',
            'enrolled_at.date'    => 'Ngày ghi danh không đúng định dạng.',
            'start_session_no.integer' => '“Bắt đầu từ buổi số” phải là số nguyên.',
            'start_session_no.min'     => '“Bắt đầu từ buổi số” tối thiểu là 1.',
            'status.in'           => 'Trạng thái không hợp lệ.',
        ];
    }
}
