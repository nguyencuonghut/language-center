<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class AttachCertificateRequest extends FormRequest
{
    public function authorize()
    {
        // Có thể kiểm tra quyền ở đây nếu cần
        return true;
    }

    public function rules()
    {
        return [
            'certificate_id' => ['required','exists:certificates,id'],
            'credential_no'  => ['nullable','string','max:191'],
            'issued_by'      => ['nullable','string','max:191'],
            'issued_at'      => ['nullable','date'],
            'expires_at'     => ['nullable','date'],
            'file'           => ['nullable','file','max:4096'],
        ];
    }

    public function messages()
    {
        return [
            'certificate_id.required' => 'Chứng chỉ là bắt buộc.',
            'certificate_id.exists'   => 'Chứng chỉ không tồn tại.',
            'credential_no.string'    => 'Số hiệu phải là một chuỗi.',
            'credential_no.max'       => 'Số hiệu không được vượt quá 191 ký tự.',
            'issued_by.string'        => 'Nơi cấp phải là một chuỗi.',
            'issued_by.max'           => 'Nơi cấp không được vượt quá 191 ký tự.',
            'issued_at.date'          => 'Ngày cấp không hợp lệ.',
            'expires_at.date'         => 'Ngày hết hạn không hợp lệ.',
            'file.file'               => 'Tệp đính kèm không hợp lệ.',
            'file.max'                => 'Kích thước tệp đính kèm không được vượt quá 4MB.',
        ];
    }
}
