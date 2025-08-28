<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'branch_id'  => ['required','integer','exists:branches,id'],
            'student_id' => ['required','integer','exists:students,id'],
            'class_id'   => ['nullable','integer','exists:classrooms,id'],
            'total'      => ['required','integer','min:0'],
            'status'     => ['required', Rule::in(['unpaid','partial','paid','refunded'])],
            'due_date'   => ['nullable','date'],
        ];
    }

    public function messages(): array
    {
        return (new StoreInvoiceRequest())->messages();
    }
}
