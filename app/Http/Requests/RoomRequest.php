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
}
