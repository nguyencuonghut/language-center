<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreTransferRequest;
use App\Models\Enrollment;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Student;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentTransferController extends Controller
{
    /**
     * POST /manager/students/{student}/transfer
     */
    public function store(StoreTransferRequest $request, Student $student)
    {
        $data = $request->validated();

        // Lấy lớp nguồn/đích (để kiểm tra/ghi chú)
        $fromClass = Classroom::findOrFail($data['from_class_id']);
        $toClass   = Classroom::findOrFail($data['to_class_id']);

        // Phải đang có ghi danh ở lớp nguồn
        $fromEnroll = Enrollment::where('class_id', $fromClass->id)
            ->where('student_id', $student->id)
            ->first();

        if (!$fromEnroll) {
            return back()->with('error', 'Học viên chưa ghi danh ở lớp nguồn.');
        }

        // Không được ghi danh trùng ở lớp đích
        $existsAtTarget = Enrollment::where('class_id', $toClass->id)
            ->where('student_id', $student->id)
            ->exists();

        if ($existsAtTarget) {
            return back()->with('error', 'Học viên đã tồn tại trong lớp đích.');
        }

        $student   = Student::findOrFail($student->id);
        $classroom = !empty($data['to_class_id']) ? Classroom::findOrFail($data['to_class_id']) : null;

        DB::transaction(function () use ($student, $data, $fromClass, $toClass, $fromEnroll, $classroom) {
            // 1) Tạo record transfer trước
            $transfer = Transfer::create([
                'student_id' => $student->id,
                'from_class_id' => $fromClass->id,
                'to_class_id' => $toClass->id,
                'effective_date' => $data['effective_date'],
                'start_session_no' => (int) $data['start_session_no'],
                'reason' => $data['note'] ?? null,
                'status' => 'active',
                'created_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            // 2) Đánh dấu ghi danh cũ là "transferred"
            $fromEnroll->update([
                'status' => 'transferred',
            ]);

            // 3) Tạo ghi danh mới ở lớp đích
            Enrollment::create([
                'student_id'       => $student->id,
                'class_id'         => $toClass->id,
                'enrolled_at'      => $data['effective_date'],
                'start_session_no' => (int) $data['start_session_no'],
                'status'           => 'active',
            ]);

            // 4) (Tùy chọn) Tạo hoá đơn điều chỉnh
            if (!empty($data['create_adjustments'])) {
                $invoice = Invoice::create([
                    'branch_id'  => $toClass->branch_id,
                    'student_id' => $student->id,
                    'class_id'   => null, // để trống, vì là chứng từ điều chỉnh
                    'total'      => 0,
                    'status'     => 'unpaid',
                    'due_date'   => null,
                    'code'      => 'INV-' . $student->code . ($classroom ? ('-' . $classroom->code) : ''),
                ]);

                // Link invoice với transfer
                $transfer->update(['invoice_id' => $invoice->id]);

                // Ghi chú điều chỉnh out/in – amount = 0 (để người phụ trách tính tiếp)
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'type'        => 'transfer_out',
                    'description' => 'Điều chỉnh chuyển lớp: rời ' . ($fromClass->code ?? ('Lớp #' . $fromClass->id)),
                    'qty'         => 1,
                    'unit_price'  => 0,
                    'amount'      => 0,
                ]);

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'type'        => 'transfer_in',
                    'description' => 'Điều chỉnh chuyển lớp: vào ' . ($toClass->code ?? ('Lớp #' . $toClass->id)),
                    'qty'         => 1,
                    'unit_price'  => 0,
                    'amount'      => 0,
                ]);
            }
        });

        return back()->with(
            'success',
            'Đã chuyển lớp thành công: ' .
            ($fromClass->code ?? ('#' . $fromClass->id)) . ' → ' .
            ($toClass->code ?? ('#' . $toClass->id))
        );
    }
}
