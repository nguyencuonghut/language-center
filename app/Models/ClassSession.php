<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSession extends Model
{
    protected $fillable = [
        'class_id', 'session_no', 'date', 'start_time', 'end_time', 'room_id', 'status', 'note'
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class, 'class_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Scope: tìm các buổi cùng phòng, cùng ngày, bị chồng lấn khoảng thời gian.
     * Điều kiện chồng lấn: (startA < endB) && (endA > startB)
     */
    public function scopeOverlapping($q, array $attrs)
    {
        // Yêu cầu đủ 4 trường
        if (empty($attrs['room_id']) || empty($attrs['date']) || empty($attrs['start_time']) || empty($attrs['end_time'])) {
            return $q->whereRaw('1 = 0'); // không trả kết quả để khỏi gây false-positive
        }

        return $q->where('room_id', $attrs['room_id'])
            ->where('date', $attrs['date'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($w) use ($attrs) {
                $w->where('start_time', '<', $attrs['end_time'])
                  ->where('end_time',   '>', $attrs['start_time']);
            });
    }
}
