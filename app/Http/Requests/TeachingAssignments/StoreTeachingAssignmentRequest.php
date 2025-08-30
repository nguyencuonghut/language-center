<?php

namespace App\Http\Requests\TeachingAssignments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tạm thời không dùng Policy
        return true;
    }

    public function rules(): array
    {
        $classId   = $this->input('class_id');
        $teacherId = $this->input('teacher_id');
        $effFrom   = $this->input('effective_from');

        return [
            'class_id' => ['required','integer','exists:classes,id'],
            'teacher_id' => ['required','integer','exists:users,id'],
            'rate_per_session' => ['required','integer','min:0'],

            'effective_from' => ['nullable','date'],
            'effective_to'   => ['nullable','date','after_or_equal:effective_from'],

            // Đảm bảo không trùng (class_id, teacher_id, effective_from)
            // (trùng với ràng buộc unique ở DB: assign_unique)
            // Lưu ý: nếu effective_from = null, DB vẫn chấp nhận unique theo (class, teacher, null)
            // nên ở đây cũng kiểm tra tương tự.
            'unique_comp' => [
                Rule::unique('teaching_assignments')
                    ->where(fn($q) =>
                        $q->where('class_id', $classId)
                          ->where('teacher_id', $teacherId)
                          ->where('effective_from', $effFrom)
                    )
            ],
        ];
    }

    public function withValidator($validator)
    {
        // Di chuyển lỗi từ unique_comp sang field dễ hiểu (effective_from)
        $validator->after(function ($v) {
            if ($v->errors()->has('unique_comp')) {
                $v->errors()->add('effective_from', 'Bản ghi phân công đã tồn tại (cùng lớp, giáo viên và ngày hiệu lực).');
                $v->errors()->forget('unique_comp');
            }
        });
    }

    public function messages(): array
    {
        return [
            'class_id.required' => 'Vui lòng chọn lớp.',
            'class_id.integer'  => 'Lớp không hợp lệ.',
            'class_id.exists'   => 'Lớp không tồn tại.',

            'teacher_id.required' => 'Vui lòng chọn giáo viên.',
            'teacher_id.integer'  => 'Giáo viên không hợp lệ.',
            'teacher_id.exists'   => 'Giáo viên không tồn tại.',

            'rate_per_session.required' => 'Vui lòng nhập đơn giá/buổi.',
            'rate_per_session.integer'  => 'Đơn giá/buổi phải là số nguyên.',
            'rate_per_session.min'      => 'Đơn giá/buổi không được âm.',

            'effective_from.date' => 'Ngày hiệu lực không đúng định dạng.',
            'effective_to.date'   => 'Ngày kết thúc không đúng định dạng.',
            'effective_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày hiệu lực.',
        ];
    }

    public function attributes(): array
    {
        return [
            'class_id' => 'lớp',
            'teacher_id' => 'giáo viên',
            'rate_per_session' => 'đơn giá/buổi',
            'effective_from' => 'ngày hiệu lực',
            'effective_to' => 'ngày kết thúc',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        // Ép kiểu an toàn
        $data['class_id'] = (int) $data['class_id'];
        $data['teacher_id'] = (int) $data['teacher_id'];
        $data['rate_per_session'] = (int) $data['rate_per_session'];
        return $data;
    }
}
