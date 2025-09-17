<?php

namespace App\Http\Requests\Certificate;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['required','string','max:191','unique:certificates,code'],
            'name' => ['required','string','max:191'],
            'description' => ['nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã chứng chỉ là bắt buộc.',
            'code.unique' => 'Mã chứng chỉ đã tồn tại.',
            'name.required' => 'Tên chứng chỉ là bắt buộc.',
            'name.max' => 'Tên chứng chỉ không được vượt quá 191 ký tự.',
            'description.string' => 'Mô tả phải là một chuỗi.',
        ];
    }
}
