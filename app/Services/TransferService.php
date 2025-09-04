<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\Transfer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferService
{
    /**
     * Tạo transfer mới
     */
    public function createTransfer(array $data): Transfer
    {
        $student = Student::findOrFail($data['student_id']);
        $fromClass = Classroom::findOrFail($data['from_class_id']);
        $toClass = Classroom::findOrFail($data['to_class_id']);

        // Validate business rules
        $this->validateTransferEligibility($student, $fromClass, $toClass);

        return DB::transaction(function () use ($data, $student, $fromClass, $toClass) {
            // 1) Tạo transfer record
            $transfer = Transfer::create([
                'student_id' => $student->id,
                'from_class_id' => $fromClass->id,
                'to_class_id' => $toClass->id,
                'effective_date' => $data['effective_date'],
                'start_session_no' => $data['start_session_no'] ?? 1,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'active',
                'created_by' => Auth::id(),
                'processed_at' => now(),
                'transfer_fee' => $data['transfer_fee'] ?? 0,
            ]);

            // 2) Update enrollment statuses
            $this->updateEnrollmentForTransfer($student, $fromClass, $toClass, $data);

            // 3) Create invoice if needed
            if (!empty($data['create_adjustments']) || !empty($data['transfer_fee'])) {
                $invoice = $this->createTransferInvoice($transfer, $fromClass, $toClass, $data);
                $transfer->update(['invoice_id' => $invoice->id]);
            }

            return $transfer;
        });
    }

    /**
     * Hoàn tác transfer
     */
    public function revertTransfer(Transfer $transfer): void
    {
        if (!$transfer->canRevert()) {
            throw new \Exception('Không thể hoàn tác transfer này.');
        }

        DB::transaction(function () use ($transfer) {
            $student = $transfer->student;
            $fromClass = $transfer->fromClass;
            $toClass = $transfer->toClass;

            // 1) Xoá enrollment lớp đích
            Enrollment::where('student_id', $student->id)
                ->where('class_id', $toClass->id)
                ->delete();

            // 2) Khôi phục enrollment lớp nguồn
            $this->restoreOriginalEnrollment($student, $fromClass);

            // 3) Xử lý invoice
            $this->cleanupTransferInvoice($transfer);

            // 4) Update transfer status
            $transfer->update([
                'status' => 'reverted',
                'reverted_at' => now(),
                'reverted_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Đổi hướng transfer
     */
    public function retargetTransfer(Transfer $transfer, Classroom $newTargetClass, array $data = []): void
    {
        if (!$transfer->canRetarget()) {
            throw new \Exception('Không thể retarget transfer này.');
        }

        DB::transaction(function () use ($transfer, $newTargetClass, $data) {
            $student = $transfer->student;
            $oldTargetClass = $transfer->toClass;

            // 1) Xoá enrollment lớp đích cũ
            Enrollment::where('student_id', $student->id)
                ->where('class_id', $oldTargetClass->id)
                ->delete();

            // 2) Tạo enrollment lớp đích mới
            Enrollment::create([
                'student_id' => $student->id,
                'class_id' => $newTargetClass->id,
                'enrolled_at' => now()->toDateString(),
                'start_session_no' => $data['start_session_no'] ?? 1,
                'status' => 'active',
            ]);

            // 3) Xử lý invoice cũ
            $this->cleanupTransferInvoice($transfer);

            // 4) Tạo invoice mới nếu cần
            $newInvoiceId = null;
            if (!empty($data['amount']) && $data['amount'] > 0) {
                $invoice = $this->createRetargetInvoice($transfer, $newTargetClass, $data);
                $newInvoiceId = $invoice->id;
            }

            // 5) Update transfer record
            $transfer->update([
                'status' => 'retargeted',
                'retargeted_to_class_id' => $newTargetClass->id,
                'retargeted_at' => now(),
                'retargeted_by' => Auth::id(),
                'invoice_id' => $newInvoiceId,
                'transfer_fee' => $data['amount'] ?? 0,
            ]);
        });
    }

    /**
     * Validate eligibility for transfer
     */
    private function validateTransferEligibility(Student $student, Classroom $fromClass, Classroom $toClass): void
    {
        // Check existing enrollment in source class
        $fromEnroll = Enrollment::where('student_id', $student->id)
            ->where('class_id', $fromClass->id)
            ->where('status', 'active')
            ->first();

        if (!$fromEnroll) {
            throw new \Exception('Học viên chưa ghi danh ở lớp nguồn.');
        }

        // Check for duplicate enrollment in target class
        $existsAtTarget = Enrollment::where('student_id', $student->id)
            ->where('class_id', $toClass->id)
            ->exists();

        if ($existsAtTarget) {
            throw new \Exception('Học viên đã tồn tại trong lớp đích.');
        }

        // Check for existing active transfer
        $activeTransfer = Transfer::active()
            ->where('student_id', $student->id)
            ->exists();

        if ($activeTransfer) {
            throw new \Exception('Học viên đang có transfer đang hoạt động.');
        }
    }

    /**
     * Update enrollments for transfer
     */
    private function updateEnrollmentForTransfer(Student $student, Classroom $fromClass, Classroom $toClass, array $data): void
    {
        // Mark source enrollment as transferred
        Enrollment::where('student_id', $student->id)
            ->where('class_id', $fromClass->id)
            ->update(['status' => 'transferred']);

        // Create target enrollment
        Enrollment::create([
            'student_id' => $student->id,
            'class_id' => $toClass->id,
            'enrolled_at' => $data['effective_date'],
            'start_session_no' => $data['start_session_no'] ?? 1,
            'status' => 'active',
        ]);
    }

    /**
     * Restore original enrollment
     */
    private function restoreOriginalEnrollment(Student $student, Classroom $fromClass): void
    {
        $fromEnroll = Enrollment::where('student_id', $student->id)
            ->where('class_id', $fromClass->id)
            ->first();

        if ($fromEnroll) {
            if (method_exists($fromEnroll, 'restore')) {
                $fromEnroll->restore();
            }
            $fromEnroll->update(['status' => 'active']);
        } else {
            // Create new enrollment if not exists
            Enrollment::create([
                'student_id' => $student->id,
                'class_id' => $fromClass->id,
                'enrolled_at' => now()->toDateString(),
                'start_session_no' => 1,
                'status' => 'active',
            ]);
        }
    }

    /**
     * Create transfer invoice
     */
    private function createTransferInvoice(Transfer $transfer, Classroom $fromClass, Classroom $toClass, array $data): Invoice
    {
        $invoice = Invoice::create([
            'branch_id' => $toClass->branch_id,
            'student_id' => $transfer->student_id,
            'class_id' => null, // Adjustment invoice
            'total' => $data['transfer_fee'] ?? 0,
            'status' => 'unpaid',
            'due_date' => $data['due_date'] ?? null,
            'note' => $data['notes'] ?? null,
            'code' => 'TRF-' . $transfer->student->code . '-' . $toClass->code,
        ]);

        // Create invoice items for audit
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => 'transfer_out',
            'description' => 'Điều chỉnh chuyển lớp: rời ' . $fromClass->code,
            'qty' => 1,
            'unit_price' => 0,
            'amount' => 0,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'type' => 'transfer_in',
            'description' => 'Điều chỉnh chuyển lớp: vào ' . $toClass->code,
            'qty' => 1,
            'unit_price' => $data['transfer_fee'] ?? 0,
            'amount' => $data['transfer_fee'] ?? 0,
        ]);

        return $invoice;
    }

    /**
     * Create retarget invoice
     */
    private function createRetargetInvoice(Transfer $transfer, Classroom $newTargetClass, array $data): Invoice
    {
        return Invoice::create([
            'branch_id' => $newTargetClass->branch_id,
            'student_id' => $transfer->student_id,
            'class_id' => $newTargetClass->id,
            'total' => $data['amount'],
            'status' => 'unpaid',
            'due_date' => $data['due_date'] ?? now()->addDays(7)->toDateString(),
            'note' => $data['note'] ?? null,
            'code' => 'RTG-' . $transfer->student->code . '-' . $newTargetClass->code,
        ]);
    }

    /**
     * Cleanup transfer invoice
     */
    private function cleanupTransferInvoice(Transfer $transfer): void
    {
        if ($transfer->invoice_id) {
            $invoice = Invoice::find($transfer->invoice_id);
            if ($invoice && $invoice->status === 'unpaid' && !$invoice->payments()->exists()) {
                $invoice->invoiceItems()->delete();
                $invoice->delete();
            }
        }
    }

    /**
     * Get transfer statistics
     */
    public function getTransferStats(array $filters = []): array
    {
        $query = Transfer::query();

        if (!empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        $total = $query->count();
        $active = $query->clone()->where('status', 'active')->count();
        $reverted = $query->clone()->where('status', 'reverted')->count();
        $retargeted = $query->clone()->where('status', 'retargeted')->count();

        return [
            'total' => $total,
            'active' => $active,
            'reverted' => $reverted,
            'retargeted' => $retargeted,
            'success_rate' => $total > 0 ? round((($active + $retargeted) / $total) * 100, 2) : 0,
        ];
    }
}
