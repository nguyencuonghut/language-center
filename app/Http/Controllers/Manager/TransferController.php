<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\RevertTransferRequest;
use App\Http\Requests\Transfer\RetargetTransferRequest;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($student, $fromClass, $toClass) {
            // 1) Kiểm tra enrollment ở lớp đích (để xoá)
            $toEnroll = Enrollment::where('student_id', $student->id)->where('class_id', $toClass->id)->first();
            if ($toEnroll) {
                // Cấm hoàn tác nếu đã có điểm danh ở buổi thuộc lớp đích
                $hasAttendance = Attendance::whereHas('session', function($q) use ($toClass) {
                        $q->where('class_id', $toClass->id);
                    })
                    ->where('student_id', $student->id)
                    ->exists();

                if ($hasAttendance) {
                    abort(422, 'Không thể hoàn tác: học viên đã có điểm danh ở lớp đích.');
                }

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

            // 3) Hoá đơn chuyển lớp (nếu có): tìm invoice của học viên liên quan lớp đích và chưa thanh toán → xoá
            $invoices = Invoice::where('student_id', $student->id)
                ->where(function($q) use ($toClass) {
                    $q->where('class_id', $toClass->id)->orWhereNull('class_id');
                })
                ->whereIn('status', ['unpaid'])
                ->get();

            foreach ($invoices as $inv) {
                // Nếu chỉ là invoice riêng cho phí chuyển / điều chỉnh thì có thể xoá
                // Nếu bạn muốn chắc chắn là invoice này do chuyển lớp tạo ra, có thể dò thêm invoice_items type 'transfer_in'/'transfer_out'.
                $hasPayments = $inv->payments()->exists();
                if (!$hasPayments) {
                    $inv->invoiceItems()->delete();
                    $inv->delete();
                }
            }
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

        DB::transaction(function () use ($student, $fromClass, $oldTo, $newTo, $data) {
            // 1) Giống revert: gỡ enrollment/lợi tức ở lớp đích cũ (chưa có attendance & chưa thu tiền)
            $oldToEnroll = Enrollment::where('student_id', $student->id)->where('class_id', $oldTo->id)->first();

            if ($oldToEnroll) {
                $hasAttendance = Attendance::whereHas('session', fn($q) => $q->where('class_id', $oldTo->id))
                    ->where('student_id', $student->id)
                    ->exists();
                if ($hasAttendance) {
                    abort(422, 'Không thể sửa: học viên đã có điểm danh ở lớp đích cũ.');
                }
                $oldToEnroll->delete();
            }

            // Hoá đơn liên quan lớp đích cũ (unpaid) → xoá
            $oldInv = Invoice::where('student_id', $student->id)
                ->where(function($q) use ($oldTo) {
                    $q->where('class_id', $oldTo->id)->orWhereNull('class_id');
                })
                ->where('status', 'unpaid')
                ->get();

            foreach ($oldInv as $inv) {
                if (!$inv->payments()->exists()) {
                    $inv->invoiceItems()->delete();
                    $inv->delete();
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

            // 3) (Tuỳ chính sách) tạo invoice phí chuyển mới nếu có yêu cầu
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

                // (tuỳ chọn) thêm invoice_items chi tiết
                // InvoiceItem::create([
                //     'invoice_id'  => $inv->id,
                //     'type'        => 'transfer_in',
                //     'description' => 'Phí chuyển lớp',
                //     'qty'         => 1,
                //     'unit_price'  => (int)$data['amount'],
                //     'amount'      => (int)$data['amount'],
                // ]);
            }
        });

        return back()->with('success', 'Đã cập nhật chuyển lớp sang lớp mới.');
    }
}
