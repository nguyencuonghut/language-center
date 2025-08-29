<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['sometimes','required','in:tuition,adjust,transfer_out,transfer_in,refund'],
            'description' => ['sometimes','nullable','string','max:255'],
            'qty'         => ['sometimes','required','integer','min:1'],
            'unit_price'  => ['sometimes','required','integer'],
            'amount'      => ['sometimes','nullable','integer'],
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
