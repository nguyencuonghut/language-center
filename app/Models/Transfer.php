<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'from_class_id',
        'to_class_id',
        'effective_date',
        'start_session_no',
        'reason',
        'notes',
        'status',
        'created_by',
        'processed_at',
        'transfer_fee',
        'invoice_id',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'processed_at' => 'datetime',
        'reverted_at' => 'datetime',
        'retargeted_at' => 'datetime',
        'transfer_fee' => 'decimal:2',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function fromClass()
    {
        return $this->belongsTo(Classroom::class, 'from_class_id');
    }

    public function toClass()
    {
        return $this->belongsTo(Classroom::class, 'to_class_id');
    }

    public function retargetedToClass()
    {
        return $this->belongsTo(Classroom::class, 'retargeted_to_class_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revertedBy()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    public function retargetedBy()
    {
        return $this->belongsTo(User::class, 'retargeted_by');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeReverted($query)
    {
        return $query->where('status', 'reverted');
    }

    public function scopeRetargeted($query)
    {
        return $query->where('status', 'retargeted');
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // Business Logic Methods
    public function canRevert(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check if student has attendance in target class
        return !$this->hasAttendanceInTargetClass();
    }

    public function canRetarget(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check if student has attendance in target class
        return !$this->hasAttendanceInTargetClass();
    }

    private function hasAttendanceInTargetClass(): bool
    {
        return \App\Models\Attendance::whereHas('session', function($q) {
            $q->where('class_id', $this->to_class_id);
        })->where('student_id', $this->student_id)->exists();
    }

    public function getEffectiveTargetClassId(): int
    {
        // If retargeted, return the new target class
        if ($this->status === 'retargeted' && $this->retargeted_to_class_id) {
            return $this->retargeted_to_class_id;
        }

        // Otherwise return original target class
        return $this->to_class_id;
    }

    public function getEffectiveTargetClass()
    {
        if ($this->status === 'retargeted' && $this->retargeted_to_class_id) {
            return $this->retargetedToClass();
        }

        return $this->toClass();
    }

    // Static helpers
    public static function getActiveTransferForStudent(int $studentId): ?self
    {
        return static::active()->forStudent($studentId)->first();
    }

    public static function getTransferHistory(int $studentId)
    {
        return static::forStudent($studentId)
            ->with(['fromClass', 'toClass', 'retargetedToClass', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
