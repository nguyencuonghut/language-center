<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Models\ClassSchedule;

class ClassScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // Có thể siết bằng policy Classroom sau
    }

    public function rules(): array
    {
        $classroom   = $this->route('classroom');       // từ nested route {classroom}
        $schedule    = $this->route('schedule');        // khi update: {schedule}
        $scheduleId  = $schedule?->id;
        $classroomId = (int) ($classroom?->id ?? $this->input('class_id'));

        // Lấy sẵn input để dùng cho unique theo cặp (weekday + time range + class)
        $weekday    = $this->input('weekday');
        $startTime  = $this->input('start_time');
        $endTime    = $this->input('end_time');

        return [
            'class_id'   => ['required', 'integer', 'exists:classrooms,id', Rule::in([$classroomId])],
            'weekday'    => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i'],

            // Ngăn bản ghi trùng y hệt trong cùng lớp
            Rule::unique('class_schedules')
                ->where(fn($q) => $q->where('class_id', $classroomId)
                                    ->where('weekday', $weekday)
                                    ->where('start_time', $startTime)
                                    ->where('end_time', $endTime))
                ->ignore($scheduleId),
        ];
    }

    public function attributes(): array
    {
        return [
            'class_id'   => 'lớp học',
            'weekday'    => 'thứ trong tuần',
            'start_time' => 'giờ bắt đầu',
            'end_time'   => 'giờ kết thúc',
        ];
    }

    public function messages(): array
    {
        return [
            'class_id.required'   => 'Vui lòng chọn lớp học.',
            'class_id.exists'     => 'Lớp học không hợp lệ.',
            'class_id.in'         => 'Lịch phải thuộc đúng lớp trong URL.',

            'weekday.required'    => 'Vui lòng chọn thứ trong tuần.',
            'weekday.integer'     => 'Thứ trong tuần phải là số.',
            'weekday.between'     => 'Thứ trong tuần phải từ 0 (Chủ nhật) đến 6 (Thứ bảy).',

            'start_time.required' => 'Vui lòng nhập giờ bắt đầu.',
            'start_time.date_format' => 'Giờ bắt đầu phải theo định dạng HH:mm (vd: 08:00).',

            'end_time.required'   => 'Vui lòng nhập giờ kết thúc.',
            'end_time.date_format'=> 'Giờ kết thúc phải theo định dạng HH:mm (vd: 09:30).',

            // unique combo
            'unique'              => 'Lịch này đã tồn tại (trùng thứ và khung giờ trong cùng lớp).',
        ];
    }

    /**
     * Kiểm tra nghiệp vụ bổ sung:
     * - start_time < end_time
     * - Không chồng chéo khung giờ trong cùng lớp & cùng weekday.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $classroom   = $this->route('classroom');
            $schedule    = $this->route('schedule');
            $scheduleId  = $schedule?->id;
            $classroomId = (int) ($classroom?->id ?? $this->input('class_id'));

            $weekday   = (int) $this->input('weekday');
            $start     = $this->input('start_time');
            $end       = $this->input('end_time');

            // 1) start < end
            if ($start && $end && strcmp($start, $end) >= 0) {
                $v->errors()->add('end_time', 'Giờ kết thúc phải lớn hơn giờ bắt đầu.');
                return;
            }

            // 2) Overlap check trong cùng lớp & cùng weekday
            // Điều kiện chồng chéo: (start < existing_end) AND (end > existing_start)
            if ($classroomId && $weekday !== null && $start && $end) {
                $overlapExists = ClassSchedule::query()
                    ->where('class_id', $classroomId)
                    ->where('weekday', $weekday)
                    ->when($scheduleId, fn($q) => $q->where('id', '!=', $scheduleId))
                    ->where(function ($q) use ($start, $end) {
                        $q->where('start_time', '<', $end)
                          ->where('end_time',   '>', $start);
                    })
                    ->exists();

                if ($overlapExists) {
                    $v->errors()->add('start_time', 'Khung giờ bị chồng chéo với lịch khác trong cùng ngày.');
                    $v->errors()->add('end_time',   'Khung giờ bị chồng chéo với lịch khác trong cùng ngày.');
                }
            }
        });
    }

    /**
     * Merge class_id từ route vào input để controller không cần tự chèn lại.
     */
    protected function prepareForValidation(): void
    {
        $classroom = $this->route('classroom');
        if ($classroom && !$this->filled('class_id')) {
            $this->merge(['class_id' => $classroom->id]);
        }
    }
}
