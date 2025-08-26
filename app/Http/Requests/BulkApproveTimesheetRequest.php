<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkApproveTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && $u->hasAnyRole(['admin', 'manager']);
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:teacher_timesheets,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'   => 'Vui lòng chọn ít nhất 1 bản ghi.',
            'ids.array'      => 'Dữ liệu danh sách không hợp lệ.',
            'ids.min'        => 'Vui lòng chọn ít nhất 1 bản ghi.',
            'ids.*.integer'  => 'ID không hợp lệ.',
            'ids.*.distinct' => 'Danh sách chứa ID trùng lặp.',
            'ids.*.exists'   => 'Một hoặc nhiều bản ghi không tồn tại.',
        ];
    }

    public function attributes(): array
    {
        return [
            'ids'   => 'danh sách bản ghi',
            'ids.*' => 'ID bản ghi',
        ];
    }
}
