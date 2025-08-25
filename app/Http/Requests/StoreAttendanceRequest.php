<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // đã chặn bằng middleware + policy ở route group
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items'              => ['required', 'array', 'min:1'],
            'items.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'items.*.status'     => ['required', Rule::in(['present','absent','late','excused'])],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                => 'Vui lòng gửi danh sách học viên.',
            'items.array'                   => 'Dữ liệu học viên không hợp lệ.',
            'items.min'                     => 'Cần ít nhất 1 học viên.',
            'items.*.student_id.required'   => 'Thiếu mã học viên.',
            'items.*.student_id.integer'    => 'Mã học viên không hợp lệ.',
            'items.*.student_id.exists'     => 'Học viên không tồn tại.',
            'items.*.status.required'       => 'Thiếu trạng thái điểm danh.',
            'items.*.status.in'             => 'Trạng thái điểm danh không hợp lệ.',
        ];
    }
}
