<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Chưa dùng Policy, cho phép qua middleware role:manager
        return true;
    }

    public function rules(): array
    {
        return [
            'code'   => ['required','string','max:50','unique:students,code'],
            'name'   => ['required','string','max:255'],
            'gender' => ['nullable', Rule::in(['Nam','Nữ','Khác'])],
            // DB đang là datetime (dob) — chấp nhận date hoặc datetime
            'dob'    => ['nullable','date'],
            'email'  => ['nullable','email','max:255','unique:students,email'],
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
