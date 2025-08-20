<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: gắn policy khi sẵn sàng
    }

    public function rules(): array
    {
        return [
            'date'       => ['sometimes','date'],
            'start_time' => ['sometimes','date_format:H:i'],
            'end_time'   => ['sometimes','date_format:H:i','after:start_time'],
            'room_id'    => ['nullable','integer','exists:rooms,id'],
            'status'     => ['sometimes','in:planned,taught,cancelled'],
            'note'       => ['nullable','string','max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date'              => 'Ngày không hợp lệ.',
            'start_time.date_format' => 'Giờ bắt đầu phải theo định dạng HH:mm.',
            'end_time.date_format'   => 'Giờ kết thúc phải theo định dạng HH:mm.',
            'end_time.after'         => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'room_id.exists'         => 'Phòng không hợp lệ.',
            'status.in'              => 'Trạng thái không hợp lệ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $data = $this->validated();

            // Chỉ kiểm tra chồng chéo nếu có đủ date, start_time, end_time và room_id
            if (!array_key_exists('room_id', $data) &&
                !array_key_exists('date', $data) &&
                !array_key_exists('start_time', $data) &&
                !array_key_exists('end_time', $data)) {
                return;
            }

            $session   = $this->route('session');   // App\Models\ClassSession
            $roomId    = $data['room_id']    ?? $session->room_id;
            $date      = $data['date']       ?? $session->date;
            $startTime = $data['start_time'] ?? $session->start_time;
            $endTime   = $data['end_time']   ?? $session->end_time;

            if (!$roomId || !$date || !$startTime || !$endTime) {
                return; // thiếu dữ liệu để kiểm tra
            }

            $overlap = \App\Models\ClassSession::query()
                ->where('room_id', $roomId)
                ->where('date', $date)
                // khoảng thời gian chồng nhau: A.start < B.end && A.end > B.start
                ->where('start_time', '<', $endTime)
                ->where('end_time',   '>', $startTime)
                ->where('id', '!=', $session->id)
                ->exists();

            if ($overlap) {
                $v->errors()->add('room_id', 'Khung giờ này đã có lớp khác trong cùng phòng.');
            }
        });
    }
}
