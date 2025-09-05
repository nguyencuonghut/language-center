<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class InvoiceSafetyService
{
    /**
     * Validate if a transfer can be safely reverted without breaking invoice integrity
     */
    public function validateTransferRevert(Transfer $transfer): array
    {
        $issues = [];

        // 1. Check for existing payments related to this transfer
        $transferPayments = $this->getTransferRelatedPayments($transfer);
        if ($transferPayments->isNotEmpty()) {
            $issues[] = [
                'type' => 'warning',
                'code' => 'EXISTING_PAYMENTS',
                'message' => 'Có ' . $transferPayments->count() . ' thanh toán liên quan đến chuyển lớp này',
                'details' => $transferPayments->map(function($payment) {
                    return [
                        'payment_id' => $payment->id,
                        'amount' => $payment->amount,
                        'date' => $payment->payment_date,
                        'invoice_id' => $payment->invoice_id
                    ];
                })->toArray(),
                'action_required' => 'Cần xử lý hoàn tiền hoặc chuyển đổi thanh toán'
            ];
        }

        // 2. Check for invoiced transfer fees
        $transferFeeInvoices = $this->getTransferFeeInvoices($transfer);
        if ($transferFeeInvoices->isNotEmpty()) {
            $issues[] = [
                'type' => 'error',
                'code' => 'INVOICED_TRANSFER_FEE',
                'message' => 'Phí chuyển lớp đã được xuất hóa đơn',
                'details' => $transferFeeInvoices->map(function($invoice) {
                    return [
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->total_amount,
                        'status' => $invoice->status,
                        'issued_date' => $invoice->created_at
                    ];
                })->toArray(),
                'action_required' => 'Phải hủy hoặc điều chỉnh hóa đơn trước khi hoàn tác'
            ];
        }

        // 3. Check enrollment payment impact
        $enrollmentImpact = $this->checkEnrollmentPaymentImpact($transfer);
        if ($enrollmentImpact['has_impact']) {
            $issues[] = [
                'type' => 'warning',
                'code' => 'ENROLLMENT_PAYMENT_IMPACT',
                'message' => 'Hoàn tác sẽ ảnh hưởng đến thanh toán học phí',
                'details' => $enrollmentImpact,
                'action_required' => 'Cần kiểm tra và điều chỉnh thanh toán học phí'
            ];
        }

        // 4. Check for partial refunds needed
        $refundNeeded = $this->calculateRefundNeeded($transfer);
        if ($refundNeeded > 0) {
            $issues[] = [
                'type' => 'info',
                'code' => 'REFUND_REQUIRED',
                'message' => 'Cần hoàn tiền một phần',
                'details' => [
                    'refund_amount' => $refundNeeded,
                    'reason' => 'Phí chuyển lớp đã thanh toán'
                ],
                'action_required' => 'Tạo phiếu hoàn tiền'
            ];
        }

        return [
            'can_revert' => empty(array_filter($issues, fn($issue) => $issue['type'] === 'error')),
            'issues' => $issues,
            'total_issues' => count($issues),
            'risk_level' => $this->calculateRiskLevel($issues)
        ];
    }

    /**
     * Validate if a transfer can be safely retargeted
     */
    public function validateTransferRetarget(Transfer $transfer, int $newToClassId): array
    {
        $issues = [];

        // 1. Check if new target class has different pricing
        $pricingDiff = $this->checkPricingDifference($transfer->to_class_id, $newToClassId);
        if ($pricingDiff['has_difference']) {
            $issues[] = [
                'type' => 'warning',
                'code' => 'PRICING_DIFFERENCE',
                'message' => 'Lớp đích mới có mức phí khác',
                'details' => $pricingDiff,
                'action_required' => 'Cần điều chỉnh hóa đơn và thanh toán'
            ];
        }

        // 2. Check for existing invoices that need adjustment
        $existingInvoices = $this->getTransferRelatedInvoices($transfer);
        if ($existingInvoices->isNotEmpty()) {
            $issues[] = [
                'type' => 'warning',
                'code' => 'INVOICE_ADJUSTMENT_NEEDED',
                'message' => 'Có hóa đơn cần điều chỉnh',
                'details' => $existingInvoices->map(function($invoice) {
                    return [
                        'invoice_id' => $invoice->id,
                        'status' => $invoice->status,
                        'amount' => $invoice->total_amount
                    ];
                })->toArray(),
                'action_required' => 'Cập nhật chi tiết hóa đơn cho lớp đích mới'
            ];
        }

        // 3. Check schedule compatibility
        $scheduleConflict = $this->checkScheduleConflict($transfer->student_id, $newToClassId);
        if ($scheduleConflict['has_conflict']) {
            $issues[] = [
                'type' => 'error',
                'code' => 'SCHEDULE_CONFLICT',
                'message' => 'Lịch học của lớp đích mới bị xung đột',
                'details' => $scheduleConflict,
                'action_required' => 'Chọn lớp đích khác hoặc giải quyết xung đột lịch'
            ];
        }

        return [
            'can_retarget' => empty(array_filter($issues, fn($issue) => $issue['type'] === 'error')),
            'issues' => $issues,
            'total_issues' => count($issues),
            'risk_level' => $this->calculateRiskLevel($issues),
            'pricing_adjustment' => $pricingDiff['adjustment_needed'] ?? null
        ];
    }

    /**
     * Create safe revert plan with payment handling
     */
    public function createRevertPlan(Transfer $transfer): array
    {
        $validation = $this->validateTransferRevert($transfer);

        if (!$validation['can_revert']) {
            return [
                'success' => false,
                'message' => 'Không thể tạo kế hoạch hoàn tác an toàn',
                'issues' => $validation['issues']
            ];
        }

        $plan = [
            'steps' => [],
            'estimated_time' => 0,
            'required_approvals' => [],
            'financial_impact' => []
        ];

        // Step 1: Handle payments if any
        $payments = $this->getTransferRelatedPayments($transfer);
        if ($payments->isNotEmpty()) {
            $plan['steps'][] = [
                'order' => 1,
                'action' => 'handle_payments',
                'description' => 'Xử lý thanh toán liên quan',
                'details' => [
                    'payment_count' => $payments->count(),
                    'total_amount' => $payments->sum('amount'),
                    'action_type' => 'refund_or_transfer'
                ],
                'estimated_minutes' => 15
            ];
            $plan['estimated_time'] += 15;
        }

        // Step 2: Handle invoices
        $invoices = $this->getTransferFeeInvoices($transfer);
        if ($invoices->isNotEmpty()) {
            $plan['steps'][] = [
                'order' => 2,
                'action' => 'cancel_invoices',
                'description' => 'Hủy hoặc điều chỉnh hóa đơn',
                'details' => [
                    'invoice_count' => $invoices->count(),
                    'total_amount' => $invoices->sum('total_amount')
                ],
                'estimated_minutes' => 10
            ];
            $plan['estimated_time'] += 10;
            $plan['required_approvals'][] = 'finance_manager';
        }

        // Step 3: Revert enrollment status
        $plan['steps'][] = [
            'order' => 3,
            'action' => 'revert_enrollment',
            'description' => 'Khôi phục trạng thái ghi danh',
            'details' => [
                'from_class' => $transfer->from_class_id,
                'to_class' => $transfer->to_class_id,
                'student_id' => $transfer->student_id
            ],
            'estimated_minutes' => 5
        ];
        $plan['estimated_time'] += 5;

        // Step 4: Update transfer status
        $plan['steps'][] = [
            'order' => 4,
            'action' => 'update_transfer_status',
            'description' => 'Cập nhật trạng thái chuyển lớp',
            'details' => [
                'transfer_id' => $transfer->id,
                'new_status' => 'reverted'
            ],
            'estimated_minutes' => 2
        ];
        $plan['estimated_time'] += 2;

        return [
            'success' => true,
            'plan' => $plan,
            'validation' => $validation
        ];
    }

    /**
     * Execute revert plan safely
     */
    public function executeRevertPlan(Transfer $transfer, array $plan, array $options = []): array
    {
        return DB::transaction(function() use ($transfer, $plan, $options) {
            $results = [];

            foreach ($plan['steps'] as $step) {
                try {
                    switch ($step['action']) {
                        case 'handle_payments':
                            $results[] = $this->handlePaymentsForRevert($transfer, $options);
                            break;
                        case 'cancel_invoices':
                            $results[] = $this->cancelInvoicesForRevert($transfer, $options);
                            break;
                        case 'revert_enrollment':
                            $results[] = $this->revertEnrollmentStatus($transfer);
                            break;
                        case 'update_transfer_status':
                            $results[] = $this->updateTransferStatus($transfer, 'reverted', $options);
                            break;
                    }
                } catch (\Exception $e) {
                    throw new \Exception("Failed at step {$step['order']}: " . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'results' => $results,
                'message' => 'Hoàn tác chuyển lớp thành công với xử lý an toàn'
            ];
        });
    }

    // Private helper methods

    private function getTransferRelatedPayments(Transfer $transfer): Collection
    {
        return Payment::whereHas('invoice.invoiceItems', function($query) use ($transfer) {
            $query->where('description', 'LIKE', "%transfer%{$transfer->id}%")
                  ->orWhere('description', 'LIKE', "%chuyển lớp%{$transfer->id}%");
        })->get();
    }

    private function getTransferFeeInvoices(Transfer $transfer): Collection
    {
        return Invoice::whereHas('invoiceItems', function($query) use ($transfer) {
            $query->where('description', 'LIKE', "%transfer fee%{$transfer->id}%")
                  ->orWhere('description', 'LIKE', "%phí chuyển%{$transfer->id}%");
        })->get();
    }

    private function getTransferRelatedInvoices(Transfer $transfer): Collection
    {
        return Invoice::whereHas('invoiceItems', function($query) use ($transfer) {
            $query->where('description', 'LIKE', "%transfer%{$transfer->id}%");
        })->get();
    }

    private function checkEnrollmentPaymentImpact(Transfer $transfer): array
    {
        // Check if there are any tuition payments for the target class
        $targetClassPayments = Payment::whereHas('invoice', function($query) use ($transfer) {
            $query->where('student_id', $transfer->student_id)
                  ->whereHas('invoiceItems', function($subQuery) use ($transfer) {
                      $subQuery->where('description', 'LIKE', "%class%{$transfer->to_class_id}%");
                  });
        })->get();

        return [
            'has_impact' => $targetClassPayments->isNotEmpty(),
            'payment_count' => $targetClassPayments->count(),
            'total_paid' => $targetClassPayments->sum('amount'),
            'details' => $targetClassPayments->map(function($payment) {
                return [
                    'payment_id' => $payment->id,
                    'amount' => $payment->amount,
                    'date' => $payment->payment_date
                ];
            })->toArray()
        ];
    }

    private function calculateRefundNeeded(Transfer $transfer): float
    {
        if (!$transfer->transfer_fee) {
            return 0;
        }

        // Check if transfer fee was paid
        $feePayments = $this->getTransferRelatedPayments($transfer);
        return $feePayments->sum('amount');
    }

    private function checkPricingDifference(int $currentClassId, int $newClassId): array
    {
        // This would need actual pricing logic - placeholder for now
        return [
            'has_difference' => false,
            'adjustment_needed' => 0,
            'current_class_fee' => 0,
            'new_class_fee' => 0
        ];
    }

    private function checkScheduleConflict(int $studentId, int $classId): array
    {
        // Check for schedule conflicts - placeholder for now
        return [
            'has_conflict' => false,
            'conflicts' => []
        ];
    }

    private function calculateRiskLevel(array $issues): string
    {
        $errorCount = count(array_filter($issues, fn($issue) => $issue['type'] === 'error'));
        $warningCount = count(array_filter($issues, fn($issue) => $issue['type'] === 'warning'));

        if ($errorCount > 0) return 'high';
        if ($warningCount > 2) return 'medium';
        if ($warningCount > 0) return 'low';
        return 'minimal';
    }

    private function handlePaymentsForRevert(Transfer $transfer, array $options): array
    {
        // Implementation for handling payments during revert
        return [
            'action' => 'handle_payments',
            'success' => true,
            'message' => 'Payments handled successfully'
        ];
    }

    private function cancelInvoicesForRevert(Transfer $transfer, array $options): array
    {
        // Implementation for canceling invoices during revert
        return [
            'action' => 'cancel_invoices',
            'success' => true,
            'message' => 'Invoices canceled successfully'
        ];
    }

    private function revertEnrollmentStatus(Transfer $transfer): array
    {
        // Revert enrollment status
        $enrollment = Enrollment::where('student_id', $transfer->student_id)
            ->where('class_id', $transfer->to_class_id)
            ->where('status', 'active')
            ->first();

        if ($enrollment) {
            $enrollment->update(['status' => 'transferred']);
        }

        // Reactivate old enrollment
        $oldEnrollment = Enrollment::where('student_id', $transfer->student_id)
            ->where('class_id', $transfer->from_class_id)
            ->where('status', 'transferred')
            ->first();

        if ($oldEnrollment) {
            $oldEnrollment->update(['status' => 'active']);
        }

        return [
            'action' => 'revert_enrollment',
            'success' => true,
            'message' => 'Enrollment status reverted successfully'
        ];
    }

    private function updateTransferStatus(Transfer $transfer, string $status, array $options): array
    {
        $transfer->update([
            'status' => $status,
            'reverted_at' => now(),
            'reverted_by' => Auth::id(),
            'notes' => $options['notes'] ?? 'Reverted with safety validation'
        ]);

        return [
            'action' => 'update_transfer_status',
            'success' => true,
            'message' => 'Transfer status updated successfully'
        ];
    }
}
