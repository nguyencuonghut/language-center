<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HolidayStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'scope' => ['required', 'in:global,branch,class'],
            'branch_id' => ['nullable', 'required_if:scope,branch', 'exists:branches,id'],
            'class_id' => ['nullable', 'required_if:scope,class', 'exists:classrooms,id'],
            'recurring_yearly' => ['boolean'],
        ];
    }
}
