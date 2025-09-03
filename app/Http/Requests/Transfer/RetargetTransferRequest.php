<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

class RetargetTransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'student_id'      => ['required','integer','exists:students,id'],
            'from_class_id'   => ['required','integer','exists:classrooms,id'],     // lớp cũ
            'old_to_class_id' => ['required','integer','exists:classrooms,id'],     // lớp đích sai
            'new_to_class_id' => ['required','integer','exists:classrooms,id','different:old_to_class_id'], // lớp đích đúng
            'start_session_no'=> ['nullable','integer','min:1'],
            'due_date'        => ['nullable','date'],
            'amount'          => ['nullable','integer','min:0'], // nếu có phí chuyển
            'note'            => ['nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required'      => 'Vui lòng chọn học viên.',
            'from_class_id.required'   => 'Thiếu lớp nguồn.',
            'old_to_class_id.required' => 'Thiếu lớp đích cũ.',
            'new_to_class_id.required' => 'Thiếu lớp đích mới.',
            'new_to_class_id.different'=> 'Lớp đích mới phải khác lớp đích cũ.',
            'start_session_no.min'     => 'Buổi bắt đầu tối thiểu là 1.',
            'due_date.date'            => 'Hạn thanh toán không đúng định dạng.',
            'amount.min'               => 'Số tiền không hợp lệ.',
        ];
    }
}
