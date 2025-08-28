<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'method' => ['required', Rule::in(['cash','bank','momo','zalopay'])],
            'paid_at'=> ['nullable','date'], // nhận Y-m-d hoặc Y-m-d H:i:s
            'amount' => ['required','integer','min:1'],
            'ref_no' => ['nullable','string','max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'method.in'       => 'Phương thức thanh toán không hợp lệ.',
            'paid_at.date'    => 'Ngày thanh toán không đúng định dạng.',
            'amount.required' => 'Vui lòng nhập số tiền thanh toán.',
            'amount.integer'  => 'Số tiền phải là số nguyên.',
            'amount.min'      => 'Số tiền tối thiểu là 1.',
            'ref_no.max'      => 'Mã tham chiếu tối đa 100 ký tự.',
        ];
    }
}
