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
        'reverted_at',
        'reverted_by',
        'retargeted_at',
        'retargeted_by',
        'retargeted_to_class_id',
        'transfer_fee',
        'invoice_id',
        // New audit trail fields
        'status_history',
        'change_log',
        'last_modified_at',
        'last_modified_by',
        'source_system',
        'admin_notes',
        'is_priority',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'processed_at' => 'datetime',
        'reverted_at' => 'datetime',
        'retargeted_at' => 'datetime',
        'transfer_fee' => 'decimal:2',
        'last_modified_at' => 'datetime',
        // JSON casts for audit trail
        'status_history' => 'array',
        'change_log' => 'array',
        'is_priority' => 'boolean',
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

    public function lastModifiedBy()
    {
        return $this->belongsTo(User::class, 'last_modified_by');
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

        // Check if student has attendance or payments in target class
        return !$this->hasAttendanceInTargetClass() && !$this->hasPaymentsInTargetClass();
    }

    public function canRetarget(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check if student has attendance or payments in target class
        return !$this->hasAttendanceInTargetClass() && !$this->hasPaymentsInTargetClass();
    }

    private function hasAttendanceInTargetClass(): bool
    {
        return \App\Models\Attendance::whereHas('session', function($q) {
            $q->where('class_id', $this->to_class_id);
        })->where('student_id', $this->student_id)->exists();
    }

    private function hasPaymentsInTargetClass(): bool
    {
        return \App\Models\Payment::whereHas('invoice', function($q) {
            $q->where('student_id', $this->student_id)
              ->where('class_id', $this->to_class_id);
        })->exists();
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

    // Audit Trail Methods
    public function logStatusChange(string $oldStatus, string $newStatus, int $userId, ?string $reason = null): void
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by' => $userId,
            'changed_at' => now()->toISOString(),
            'reason' => $reason,
        ];

        $this->update([
            'status_history' => $history,
            'last_modified_at' => now(),
            'last_modified_by' => $userId,
        ]);
    }

    public function logChange(string $field, $oldValue, $newValue, int $userId, ?string $context = null): void
    {
        $log = $this->change_log ?? [];
        $log[] = [
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $userId,
            'changed_at' => now()->toISOString(),
            'context' => $context,
        ];

        $this->update([
            'change_log' => $log,
            'last_modified_at' => now(),
            'last_modified_by' => $userId,
        ]);
    }

    public function getAuditTrail(): array
    {
        $trail = [];

        // Add creation event
        $trail[] = [
            'type' => 'created',
            'timestamp' => $this->created_at,
            'user' => $this->createdBy,
            'description' => 'Transfer created',
            'details' => [
                'from_class' => $this->fromClass->code ?? 'Unknown',
                'to_class' => $this->toClass->code ?? 'Unknown',
                'student' => $this->student->name ?? 'Unknown',
            ]
        ];

        // Add status changes
        $previousStatus = null;
        foreach ($this->status_history ?? [] as $change) {
            // Handle different data formats
            if (isset($change['from_status']) && isset($change['to_status'])) {
                // New format with from_status and to_status
                $fromStatus = $change['from_status'];
                $toStatus = $change['to_status'];
                $description = "Status changed from {$fromStatus} to {$toStatus}";
            } elseif (isset($change['status'])) {
                // Legacy format with just status
                $currentStatus = $change['status'];
                if ($previousStatus) {
                    $description = "Status changed from {$previousStatus} to {$currentStatus}";
                } else {
                    $description = "Status set to {$currentStatus}";
                }
                $previousStatus = $currentStatus;
            } else {
                // Fallback for unknown format
                $description = "Status change recorded";
            }

            $trail[] = [
                'type' => 'status_change',
                'timestamp' => $change['changed_at'] ?? now(),
                'user_id' => $change['changed_by'] ?? null,
                'description' => $description,
                'details' => $change
            ];
        }

        // Add field changes
        foreach ($this->change_log ?? [] as $change) {
            $field = $change['field'] ?? 'Unknown';

            $trail[] = [
                'type' => 'field_change',
                'timestamp' => $change['changed_at'] ?? now(),
                'user_id' => $change['changed_by'] ?? null,
                'description' => "Field '{$field}' changed",
                'details' => $change
            ];
        }

        // Sort by timestamp
        usort($trail, function($a, $b) {
            return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);
        });

        return $trail;
    }

    public function scopePriority($query)
    {
        return $query->where('is_priority', true);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source_system', $source);
    }
}
