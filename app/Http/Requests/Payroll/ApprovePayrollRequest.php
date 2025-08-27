<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'confirm' => ['required','boolean','in:1,true'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirm.required' => 'Vui lòng xác nhận duyệt kỳ lương.',
            'confirm.boolean'  => 'Giá trị xác nhận không hợp lệ.',
            'confirm.in'       => 'Bạn cần tích chọn xác nhận để duyệt.',
        ];
    }
}
