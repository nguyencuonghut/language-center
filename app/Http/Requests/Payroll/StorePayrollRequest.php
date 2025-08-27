<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Tạm thời chưa dùng Policy: cho phép khi đăng nhập (tuỳ ý siết ở Controller nếu cần role)
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'branch_id'   => ['nullable','integer','exists:branches,id'],
            'period_from' => ['required','date_format:Y-m-d'],
            'period_to'   => ['required','date_format:Y-m-d','after_or_equal:period_from'],
            // Có thể nhận thêm options: only_approved(bool), override(bool) ... (xử lý ở Controller)
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.integer'         => 'Chi nhánh không hợp lệ.',
            'branch_id.exists'          => 'Chi nhánh không tồn tại.',
            'period_from.required'      => 'Vui lòng chọn ngày bắt đầu kỳ.',
            'period_from.date_format'   => 'Ngày bắt đầu không đúng định dạng (Y-m-d).',
            'period_to.required'        => 'Vui lòng chọn ngày kết thúc kỳ.',
            'period_to.date_format'     => 'Ngày kết thúc không đúng định dạng (Y-m-d).',
            'period_to.after_or_equal'  => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
