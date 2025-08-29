<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // chưa dùng Policy
    }

    public function rules(): array
    {
        $id = $this->route('student')?->id ?? null;

        return [
            'code'   => ['required','string','max:50', Rule::unique('students','code')->ignore($id)],
            'name'   => ['required','string','max:255'],
            'gender' => ['nullable', Rule::in(['Nam','Nữ','Khác'])],
            'dob'    => ['nullable','date'],
            'email'  => ['nullable','email','max:255', Rule::unique('students','email')->ignore($id)],
            'phone'  => ['nullable','string','max:50'],
            'address'=> ['nullable','string','max:255'],
            'active' => ['nullable','boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã học viên.',
            'code.unique'   => 'Mã học viên đã tồn tại.',
            'name.required' => 'Vui lòng nhập tên học viên.',
            'gender.in'     => 'Giới tính không hợp lệ.',
            'dob.date'      => 'Ngày sinh không đúng định dạng.',
            'email.email'   => 'Email không hợp lệ.',
            'email.unique'  => 'Email đã tồn tại.',
            'phone.max'     => 'Số điện thoại quá dài.',
        ];
    }
}
