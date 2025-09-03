<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Tạm thời bỏ Policy
    }

    public function rules(): array
    {
        return [
            'from_class_id'      => ['required', 'integer', 'exists:classrooms,id'],
            'to_class_id'        => ['required', 'integer', 'different:from_class_id', 'exists:classrooms,id'],
            'start_session_no'   => ['required', 'integer', 'min:1'],
            'effective_date'     => ['required', 'date'],
            'create_adjustments' => ['boolean'],
            'note'               => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_class_id.required' => 'Thiếu thông tin lớp hiện tại.',
            'from_class_id.exists'   => 'Lớp hiện tại không tồn tại.',
            'to_class_id.required'   => 'Vui lòng chọn lớp cần chuyển tới.',
            'to_class_id.different'  => 'Lớp chuyển tới phải khác lớp hiện tại.',
            'to_class_id.exists'     => 'Lớp chuyển tới không tồn tại.',
            'start_session_no.required' => 'Vui lòng nhập buổi bắt đầu.',
            'start_session_no.min'      => 'Buổi bắt đầu phải >= 1.',
            'effective_date.required'   => 'Vui lòng chọn ngày hiệu lực.',
            'effective_date.date'       => 'Ngày hiệu lực không hợp lệ.',
            'create_adjustments.boolean'=> 'Giá trị điều chỉnh phải đúng định dạng.',
            'note.max'                  => 'Ghi chú tối đa 255 ký tự.',
        ];
    }
}
