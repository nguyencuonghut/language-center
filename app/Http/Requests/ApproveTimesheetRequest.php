<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tạm thời kiểm tra theo vai trò ở Request (chưa dùng Policy)
        $u = $this->user();
        return $u && $u->hasAnyRole(['admin', 'manager']);
    }

    public function rules(): array
    {
        // Duyệt 1 bản ghi: không cần input body
        return [];
    }

    public function messages(): array
    {
        return [
            // Giữ chỗ để mở rộng sau (VN)
        ];
    }

    public function attributes(): array
    {
        return [];
    }
}
