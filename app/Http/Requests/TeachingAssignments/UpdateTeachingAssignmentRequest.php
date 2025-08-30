<?php

namespace App\Http\Requests\TeachingAssignments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateTeachingAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tạm thời không dùng Policy
        return true;
    }

    /**
     * Lấy class_id từ route nếu FE không gửi kèm.
     */
    protected function prepareForValidation(): void
    {
        $routeClassId = $this->route('classroom')?->id; // route('admin.classrooms.assignments.update', { classroom, assignment })
        if ($routeClassId && !$this->filled('class_id')) {
            $this->merge(['class_id' => (int) $routeClassId]);
        }
    }

    public function rules(): array
    {
        $id        = $this->route('assignment')?->id ?? $this->route('teaching_assignment')?->id ?? null;

        return [
            'class_id'          => ['required','integer','exists:classrooms,id'],
            'teacher_id'        => ['required','integer','exists:users,id'],
            'rate_per_session'  => ['required','integer','min:0'],

            'effective_from'    => ['required','date'],
            'effective_to'      => ['nullable','date','after_or_equal:effective_from'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            // Nếu đã có lỗi cơ bản thì bỏ qua check unique composite
            if ($v->errors()->isNotEmpty()) return;

            $id        = $this->route('assignment')?->id ?? $this->route('teaching_assignment')?->id ?? null;
            $classId   = (int) $this->input('class_id');
            $teacherId = (int) $this->input('teacher_id');
            $effFrom   = $this->input('effective_from');

            // Kiểm tra trùng (class_id, teacher_id, effective_from), bỏ qua chính nó khi update
            $dup = DB::table('teaching_assignments')
                ->where('class_id', $classId)
                ->where('teacher_id', $teacherId)
                ->where(function ($q) use ($effFrom) {
                    // Cho phép null == null
                    if ($effFrom === null || $effFrom === '') {
                        // $q->whereNull('effective_from');
                    } else {
                        $q->where('effective_from', $effFrom);
                    }
                })
                ->when($id, fn($q) => $q->where('id', '!=', $id))
                ->exists();

            if ($dup) {
                $v->errors()->add('effective_from', 'Bản ghi phân công đã tồn tại (cùng lớp, giáo viên và ngày hiệu lực).');
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
        $data['class_id'] = (int) $data['class_id'];
        $data['teacher_id'] = (int) $data['teacher_id'];
        $data['rate_per_session'] = (int) $data['rate_per_session'];
        return $data;
    }
}
