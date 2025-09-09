<?php

namespace App\Http\Requests\SessionSubstitution;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionSubstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tạm thời chưa dùng Policy
        return true;
    }

    public function rules(): array
    {
        return [
            'substitute_teacher_id' => ['required', 'integer', 'exists:users,id'],
            'rate_override'         => ['nullable', 'integer', 'min:0'],
            'reason'                => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'substitute_teacher_id.required' => 'Vui lòng chọn giáo viên dạy thay.',
            'substitute_teacher_id.integer'  => 'Giáo viên dạy thay không hợp lệ.',
            'substitute_teacher_id.exists'   => 'Giáo viên dạy thay không tồn tại.',

            'rate_override.integer' => 'Mức tiền/buổi phải là số nguyên.',
            'rate_override.min'     => 'Mức tiền/buổi không được âm.',

            'reason.string' => 'Lý do phải là chuỗi ký tự.',
            'reason.max'    => 'Lý do tối đa 500 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'substitute_teacher_id' => 'giáo viên dạy thay',
            'rate_override'         => 'mức tiền/buổi (ghi đè)',
            'reason'                => 'lý do',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        if (isset($data['substitute_teacher_id'])) {
            $data['substitute_teacher_id'] = (int) $data['substitute_teacher_id'];
        }
        if (isset($data['rate_override']) && $data['rate_override'] !== null) {
            $data['rate_override'] = (int) $data['rate_override'];
        }
        return $data;
    }
}
