<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // tuỳ bạn gắn policy sau
    }

    public function rules(): array
    {
        return [
            // Ngày bắt đầu phát sinh (mặc định lấy classroom->start_date)
            'from_date'   => ['nullable', 'date'],
            // Số buổi cần phát sinh tối đa (mặc định = sessions_total của lớp – số đã có)
            'max_sessions'=> ['nullable', 'integer', 'min:1', 'max:500'],
            // Có xoá buổi “planned” cũ trước khi phát sinh lại không
            'reset'       => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.date'     => 'Ngày bắt đầu không hợp lệ.',
            'max_sessions.min'   => 'Số buổi tối thiểu là 1.',
            'max_sessions.max'   => 'Số buổi tối đa là 500.',
        ];
    }
}
