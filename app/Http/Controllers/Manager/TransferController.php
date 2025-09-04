<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\RevertTransferRequest;
use App\Http\Requests\Transfer\RetargetTransferRequest;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TransferController extends Controller
{
    /** Hoàn tác chuyển lớp: quay lại lớp cũ, xoá enrollment lớp đích nếu chưa phát sinh dữ liệu; xoá hoá đơn chuyển (nếu còn unpaid). */
    public function revert(RevertTransferRequest $request)
    {
        $studentId = (int) $request->student_id;
        $fromId    = (int) $request->from_class_id; // lớp trước khi chuyển
        $toId      = (int) $request->to_class_id;   // lớp đích đã chọn sai

        $student   = Student::findOrFail($studentId);
        $fromClass = Classroom::findOrFail($fromId);
        $toClass   = Classroom::findOrFail($toId);

        // Tìm transfer record active
        $transfer = Transfer::active()
            ->where('student_id', $studentId)
            ->where('from_class_id', $fromId)
            ->where('to_class_id', $toId)
            ->first();

        if (!$transfer) {
            return back()->with('error', 'Không tìm thấy transfer record để hoàn tác.');
        }

        if (!$transfer->canRevert()) {
            return back()->with('error', 'Không thể hoàn tác: học viên đã có điểm danh ở lớp đích.');
        }

        DB::transaction(function () use ($student, $fromClass, $toClass, $transfer) {
            // 1) Kiểm tra enrollment ở lớp đích (để xoá)
            $toEnroll = Enrollment::where('student_id', $student->id)->where('class_id', $toClass->id)->first();
            if ($toEnroll) {
                // Xoá enrollment lớp đích
                $toEnroll->delete();
            }

            // 2) Khôi phục enrollment lớp nguồn (nếu còn)
            $fromEnroll = Enrollment::where('student_id', $student->id)
                ->where('class_id', $fromClass->id)
                ->first();

            if ($fromEnroll) {
                // Nếu trước đó bạn dùng status 'transferred' hoặc soft delete
                if (method_exists($fromEnroll, 'restore')) $fromEnroll->restore();
                $fromEnroll->update(['status' => 'active']);
            } else {
                // Nếu đã xoá hẳn: tạo lại minimal
                Enrollment::create([
                    'student_id'       => $student->id,
                    'class_id'         => $fromClass->id,
                    'enrolled_at'      => now()->toDateString(),
                    'start_session_no' => 1,
                    'status'           => 'active',
                ]);
            }

            // 3) Hoá đơn chuyển lớp liên quan (nếu có): xoá
            if ($transfer->invoice_id) {
                $invoice = Invoice::find($transfer->invoice_id);
                if ($invoice && $invoice->status === 'unpaid' && !$invoice->payments()->exists()) {
                    $invoice->invoiceItems()->delete();
                    $invoice->delete();
                }
            }

            // 4) Cập nhật transfer status
            $transfer->update([
                'status' => 'reverted',
                'reverted_at' => now(),
                'reverted_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Đã hoàn tác chuyển lớp và khôi phục ghi danh.');
    }

    /** Sửa hướng chuyển lớp: bỏ lớp đích cũ → chuyển sang lớp đích mới (vẫn không tạo record transfers). */
    public function retarget(RetargetTransferRequest $request)
    {
        $data = $request->validated();
        $student   = Student::findOrFail($data['student_id']);
        $fromClass = Classroom::findOrFail($data['from_class_id']);
        $oldTo     = Classroom::findOrFail($data['old_to_class_id']);
        $newTo     = Classroom::findOrFail($data['new_to_class_id']);

        // Tìm transfer record active
        $transfer = Transfer::active()
            ->where('student_id', $student->id)
            ->where('from_class_id', $fromClass->id)
            ->where('to_class_id', $oldTo->id)
            ->first();

        if (!$transfer) {
            return back()->with('error', 'Không tìm thấy transfer record để retarget.');
        }

        if (!$transfer->canRetarget()) {
            return back()->with('error', 'Không thể sửa: học viên đã có điểm danh ở lớp đích cũ.');
        }

        DB::transaction(function () use ($student, $fromClass, $oldTo, $newTo, $data, $transfer) {
            // 1) Giống revert: gỡ enrollment/lợi tức ở lớp đích cũ (chưa có attendance & chưa thu tiền)
            $oldToEnroll = Enrollment::where('student_id', $student->id)->where('class_id', $oldTo->id)->first();

            if ($oldToEnroll) {
                $oldToEnroll->delete();
            }

            // Hoá đơn liên quan lớp đích cũ (unpaid) → xoá
            if ($transfer->invoice_id) {
                $invoice = Invoice::find($transfer->invoice_id);
                if ($invoice && $invoice->status === 'unpaid' && !$invoice->payments()->exists()) {
                    $invoice->invoiceItems()->delete();
                    $invoice->delete();
                }
            }

            // 2) Tạo enrollment ở lớp đích mới
            Enrollment::create([
                'student_id'       => $student->id,
                'class_id'         => $newTo->id,
                'enrolled_at'      => now()->toDateString(),
                'start_session_no' => (int)($data['start_session_no'] ?? 1),
                'status'           => 'active',
            ]);

            // 3) Cập nhật transfer record
            $updateData = [
                'status' => 'retargeted',
                'retargeted_to_class_id' => $newTo->id,
                'retargeted_at' => now(),
                'retargeted_by' => Auth::id(),
            ];

            // 4) Tạo invoice phí chuyển mới nếu có yêu cầu
            if (isset($data['amount']) && (int)$data['amount'] > 0) {
                $due = !empty($data['due_date']) ? $data['due_date'] : now()->addDays(7)->toDateString();
                $inv = Invoice::create([
                    'branch_id' => $newTo->branch_id,
                    'student_id'=> $student->id,
                    'class_id'  => $newTo->id,
                    'total'     => (int)$data['amount'],
                    'status'    => 'unpaid',
                    'due_date'  => $due,
                    'note'      => $data['note'] ?? null,
                    'code'      => 'INV-' . $student->code . '-' . $newTo->code, // thống nhất quy tắc code
                ]);

                $updateData['invoice_id'] = $inv->id;
                $updateData['transfer_fee'] = (int)$data['amount'];
            }

            $transfer->update($updateData);
        });

        return back()->with('success', 'Đã cập nhật chuyển lớp sang lớp mới.');
    }
}
