<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        // tạm thời chưa dùng Policy
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['required','in:tuition,adjust,transfer_out,transfer_in,refund'],
            'description' => ['nullable','string','max:255'],
            'qty'         => ['required','integer','min:1'],
            'unit_price'  => ['required','integer'],                   // VND
            'amount'      => ['nullable','integer'],                   // cho phép override (±)
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Vui lòng chọn loại dòng.',
            'type.in'       => 'Loại dòng không hợp lệ.',
            'description.max' => 'Mô tả tối đa 255 ký tự.',
            'qty.required'  => 'Vui lòng nhập số lượng.',
            'qty.integer'   => 'Số lượng phải là số nguyên.',
            'qty.min'       => 'Số lượng tối thiểu là 1.',
            'unit_price.required' => 'Vui lòng nhập đơn giá.',
            'unit_price.integer'  => 'Đơn giá phải là số nguyên.',
            'amount.integer' => 'Thành tiền phải là số nguyên.',
        ];
    }
}
