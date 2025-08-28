<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'type'        => ['required', Rule::in(['tuition','adjust','transfer_out','transfer_in','refund'])],
            'description' => ['nullable','string','max:255'],
            'qty'         => ['nullable','integer','min:1'],
            'unit_price'  => ['nullable','integer','min:0'],
            'amount'      => ['required','integer','min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'   => 'Vui lòng chọn loại dòng hoá đơn.',
            'type.in'         => 'Loại dòng hoá đơn không hợp lệ.',
            'description.max' => 'Mô tả tối đa 255 ký tự.',
            'qty.integer'     => 'Số lượng phải là số nguyên.',
            'qty.min'         => 'Số lượng tối thiểu là 1.',
            'unit_price.min'  => 'Đơn giá tối thiểu là 0.',
            'amount.required' => 'Vui lòng nhập thành tiền.',
            'amount.integer'  => 'Thành tiền phải là số nguyên.',
            'amount.min'      => 'Thành tiền tối thiểu là 0.',
        ];
    }
}
