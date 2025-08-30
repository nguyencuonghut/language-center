<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $teacherId = $this->route('teacher')?->id ?? null;

        return [
            'name'      => ['required', 'string', 'max:191'],
            'email'     => ['nullable', 'email', 'max:191', Rule::unique('users', 'email')->ignore($teacherId)],
            'phone'     => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($teacherId)],
            'rate'      => ['required', 'integer', 'min:0'],
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

            'rate.required'   => 'Vui lòng nhập đơn giá/buổi.',
            'rate.integer'    => 'Đơn giá/buổi phải là số nguyên.',
            'rate.min'        => 'Đơn giá/buổi không được âm.',

            'active.boolean'  => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'   => 'tên giáo viên',
            'email'  => 'email',
            'phone'  => 'số điện thoại',
            'rate'   => 'đơn giá/buổi',
            'active' => 'trạng thái',
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $data['rate']   = (int) $data['rate'];
        $data['active'] = isset($data['active']) ? (bool) $data['active'] : true;
        return $data;
    }
}
