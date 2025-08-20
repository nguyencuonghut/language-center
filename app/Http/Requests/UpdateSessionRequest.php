<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\ClassSession;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // TODO: gắn policy khi sẵn sàng
    }

    /**
     * Chuẩn hoá trước khi validate:
     * - cắt giây HH:mm:ss -> HH:mm
     */
    protected function prepareForValidation(): void
    {
        $start = $this->input('start_time');
        $end   = $this->input('end_time');

        $this->merge([
            'start_time' => $start ? substr((string) $start, 0, 5) : $start,
            'end_time'   => $end ? substr((string) $end, 0, 5) : $end,
        ]);
    }
    public function rules(): array
    {
        // chỉ validate trường có gửi lên (PUT partial)
        return [
            'date'       => ['sometimes','date'],
            'start_time' => ['sometimes','regex:/^\d{2}:\d{2}$/'],
            'end_time'   => ['sometimes','regex:/^\d{2}:\d{2}$/','after:start_time'],
            'room_id'    => ['nullable','integer','exists:rooms,id'],
            'status'     => ['sometimes','in:planned,taught,cancelled'],
            'note'       => ['nullable','string','max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.date'            => 'Ngày không hợp lệ.',
            'start_time.regex'     => 'Bắt đầu phải theo định dạng HH:mm.',
            'end_time.regex'       => 'Kết thúc phải theo định dạng HH:mm.',
            'end_time.after'       => 'Giờ kết thúc phải sau giờ bắt đầu.',
            'room_id.exists'       => 'Phòng không tồn tại.',
            'status.in'            => 'Trạng thái không hợp lệ.',
        ];
    }

    /**
     * Sau khi pass rule cơ bản, kiểm tra trùng phòng nếu có room_id + date + time.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            // gom dữ liệu đang có: ưu tiên input mới, fallback dữ liệu hiện tại
            /** @var ClassSession $session */
            $session = $this->route('session'); // model binding

            $date  = $this->input('date', $session->date);
            $start = $this->input('start_time', substr($session->start_time, 0, 5));
            $end   = $this->input('end_time',   substr($session->end_time, 0, 5));
            $room  = $this->input('room_id', $session->room_id);

            // Không set phòng thì bỏ qua check
            if (!$room || !$date || !$start || !$end) {
                return;
            }

            // Tìm xem có buổi khác (khác id hiện tại) trùng phòng + ngày + chồng lấn giờ không
            $exists = ClassSession::query()
                ->overlapping([
                    'room_id'    => $room,
                    'date'       => $date,
                    'start_time' => $start,
                    'end_time'   => $end,
                ])
                ->where('id', '!=', $session->id)
                ->exists();

            if ($exists) {
                $v->errors()->add('room_id', 'Phòng đã có lịch trùng giờ trong ngày này.');
            }
        });
    }
}
