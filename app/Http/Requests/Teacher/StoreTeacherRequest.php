<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Chưa dùng policy → luôn cho phép
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:191'],
            'email'     => ['nullable', 'email', 'max:191', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password'  => ['required', 'string', 'min:8'],
            'active'    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Vui lòng nhập tên giáo viên.',
            'name.string'     => 'Tên giáo viên không hợp lệ.',
            'name.max'        => 'Tên giáo viên tối đa 191 ký tự.',

            'email.email'     => 'Email không đúng định dạng.',
            'email.unique'    => 'Email này đã được sử dụng.',
            'email.max'       => 'Email tối đa 191 ký tự.',

            'phone.string'    => 'Số điện thoại không hợp lệ.',
            'phone.max'       => 'Số điện thoại tối đa 20 ký tự.',
            'phone.unique'    => 'Số điện thoại này đã được sử dụng.',

            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.string'   => 'Mật khẩu không hợp lệ.',
            'password.min'      => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'     => 'tên giáo viên',
            'email'    => 'email',
            'phone'    => 'số điện thoại',
            'password' => 'mật khẩu',
            'active'   => 'trạng thái hoạt động',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        // Hash password nếu có
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }
        return $data;
    }
}
