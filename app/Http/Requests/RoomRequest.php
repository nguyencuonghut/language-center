<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // tạm thời cho phép user đã đăng nhập
    }

    public function rules(): array
    {
        $room = $this->route('room'); // Model binding khi update
        $roomId = $room?->id;

        // Lấy branch_id từ input để set unique theo từng chi nhánh
        $branchId = $this->input('branch_id');

        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'code'      => [
                'required', 'string', 'max:20',
                Rule::unique('rooms', 'code')
                    ->where(fn($q) => $q->where('branch_id', $branchId))
                    ->ignore($roomId),
            ],
            'name'      => ['required', 'string', 'max:100'],
            'capacity'  => ['required', 'integer', 'min:1', 'max:1000'],
            'active'    => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'chi nhánh',
            'code'      => 'mã phòng',
            'name'      => 'tên phòng',
            'capacity'  => 'sức chứa',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Vui lòng chọn chi nhánh.',
            'branch_id.exists' => 'Chi nhánh không tồn tại.',
            'code.required' => 'Vui lòng nhập mã phòng.',
            'code.string' => 'Mã phòng phải là chuỗi ký tự.',
            'code.max' => 'Mã phòng không được vượt quá 20 ký tự.',
            'code.unique' => 'Mã phòng đã tồn tại trong chi nhánh này.',
            'name.required' => 'Vui lòng nhập tên phòng.',
            'name.string' => 'Tên phòng phải là chuỗi ký tự.',
            'name.max' => 'Tên phòng không được vượt quá 100 ký tự.',
            'capacity.required' => 'Vui lòng nhập sức chứa.',
            'capacity.integer' => 'Sức chứa phải là số nguyên.',
            'capacity.min' => 'Sức chứa phải lớn hơn hoặc bằng 1.',
            'capacity.max' => 'Sức chứa không được vượt quá 1000.',
            'active.boolean' => 'Trạng thái hoạt động phải là true hoặc false.',
        ];
    }
}
