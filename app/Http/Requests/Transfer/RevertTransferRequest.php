<?php

namespace App\Http\Requests\Transfer;

use Illuminate\Foundation\Http\FormRequest;

class RevertTransferRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'student_id'   => ['required','integer','exists:students,id'],
            'to_class_id'  => ['required','integer','exists:classrooms,id'], // lớp mới (đang muốn huỷ)
            'from_class_id'=> ['required','integer','exists:classrooms,id'], // lớp cũ (sẽ khôi phục)
            'reason'       => ['required','string','max:500'], // lý do hoàn tác
            'notes'        => ['nullable','string','max:1000'], // ghi chú thêm
        ];
    }

    public function messages(): array
    {
        return [
            'student_id.required'   => 'Vui lòng chọn học viên.',
            'student_id.exists'     => 'Học viên không tồn tại.',
            'to_class_id.required'  => 'Thiếu lớp đích cần hoàn tác.',
            'to_class_id.exists'    => 'Lớp đích không tồn tại.',
            'from_class_id.required'=> 'Thiếu lớp nguồn để khôi phục.',
            'from_class_id.exists'  => 'Lớp nguồn không tồn tại.',
            'reason.required'       => 'Vui lòng nhập lý do hoàn tác.',
            'reason.max'            => 'Lý do hoàn tác không được vượt quá 500 ký tự.',
            'notes.max'             => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
