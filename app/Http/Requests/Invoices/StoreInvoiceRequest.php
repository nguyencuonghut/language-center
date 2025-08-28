<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'branch_id'  => ['required','integer','exists:branches,id'],
            'student_id' => ['required','integer','exists:students,id'],
            'class_id'   => ['nullable','integer','exists:classrooms,id'],
            'total'      => ['required','integer','min:0'],
            'due_date'   => ['nullable','date'], // Y-m-d
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required'  => 'Vui lòng chọn chi nhánh.',
            'branch_id.exists'    => 'Chi nhánh không hợp lệ.',
            'student_id.required' => 'Vui lòng chọn học viên.',
            'student_id.exists'   => 'Học viên không hợp lệ.',
            'class_id.exists'     => 'Lớp học không hợp lệ.',
            'total.required'      => 'Vui lòng nhập tổng tiền.',
            'total.integer'       => 'Tổng tiền phải là số nguyên.',
            'total.min'           => 'Tổng tiền không được âm.',
            'due_date.date'       => 'Hạn thanh toán không đúng định dạng ngày.',
        ];
    }
}
