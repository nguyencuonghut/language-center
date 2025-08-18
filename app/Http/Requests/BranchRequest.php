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
            'address' => ['required', 'string', 'max:255'],
            'active'  => ['nullable', 'boolean'],

            // Gán manager ↔ branch
            'manager_ids'   => ['nullable', 'array'],
            'manager_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'        => 'Tên chi nhánh',
            'address'     => 'Địa chỉ',
            'active'      => 'Trạng thái',
            'manager_ids' => 'Quản lý',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Tên chi nhánh là bắt buộc.',
            'name.max'        => 'Tên chi nhánh không được vượt quá :max ký tự.',
            'name.unique'     => 'Tên chi nhánh đã tồn tại.',

            'address.required' => 'Địa chỉ là bắt buộc.',
            'address.max'     => 'Địa chỉ không được vượt quá :max ký tự.',
            'active.boolean'  => 'Trạng thái phải là true/false.',

            'manager_ids.array'        => 'Danh sách quản lý không hợp lệ.',
            'manager_ids.*.integer'    => 'Mã quản lý không hợp lệ.',
            'manager_ids.*.exists'     => 'Một số quản lý không tồn tại.',
        ];
    }
}
