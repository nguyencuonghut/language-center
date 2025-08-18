<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // Cho phép user đăng nhập; có thể siết bằng policy sau
    }

    public function rules(): array
    {
        $branch = $this->route('branch'); // {branch} từ route model binding
        $branchId = $branch?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('branches', 'name')->ignore($branchId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'active'  => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'    => 'Tên chi nhánh',
            'address' => 'Địa chỉ',
            'active'  => 'Trạng thái',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Tên chi nhánh là bắt buộc.',
            'name.max'        => 'Tên chi nhánh không được vượt quá :max ký tự.',
            'name.unique'     => 'Tên chi nhánh đã tồn tại.',

            'address.max'     => 'Địa chỉ không được vượt quá :max ký tự.',
            'active.boolean'  => 'Trạng thái phải là true/false.',
        ];
    }
}
