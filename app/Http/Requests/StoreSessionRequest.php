<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\ClassSession;

class StoreSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // đã chặn bằng middleware + policy ở route group
    }

    protected function prepareForValidation(): void
    {
        $start = $this->input('start_time');
        $end   = $this->input('end_time');

        $this->merge([
            'start_time' => $start ? substr((string)$start, 0, 5) : $start,
            'end_time'   => $end ? substr((string)$end, 0, 5) : $end,
        ]);
    }

    public function rules(): array
    {
        return [
            'date'        => ['required','date'],
            'start_time'  => ['required','regex:/^\d{2}:\d{2}$/'],
            'end_time'    => ['required','regex:/^\d{2}:\d{2}$/','after:start_time'],
            'room_id'     => ['nullable','integer','exists:rooms,id'],
            'status'      => ['nullable','in:planned,taught,cancelled'],
            'note'        => ['nullable','string','max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'       => 'Vui lòng chọn ngày.',
            'date.date'           => 'Ngày không hợp lệ.',
            'start_time.required' => 'Vui lòng nhập giờ bắt đầu.',
            'start_time.regex'    => 'Giờ bắt đầu phải theo định dạng HH:mm.',
            'end_time.required'   => 'Vui lòng nhập giờ kết thúc.',
            'end_time.regex'      => 'Giờ kết thúc phải theo định dạng HH:mm.',
            'end_time.after'      => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'room_id.exists'      => 'Phòng không tồn tại.',
            'status.in'           => 'Trạng thái không hợp lệ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $room  = $this->input('room_id');
            $date  = $this->input('date');
            $start = $this->input('start_time');
            $end   = $this->input('end_time');

            if (!$room || !$date || !$start || !$end) {
                return;
            }

            $exists = ClassSession::query()
                ->overlapping([
                    'room_id'    => $room,
                    'date'       => $date,
                    'start_time' => $start,
                    'end_time'   => $end,
                ])
                ->exists();

            if ($exists) {
                $v->errors()->add('room_id', 'Phòng đã có lịch trùng giờ trong ngày này.');
            }
        });
    }
}
