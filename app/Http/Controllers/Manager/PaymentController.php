<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\StudentLedger;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Invoice $invoice)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $invoice, $data) {
            $payment = $invoice->payments()->create([
                'method'  => $data['method'],
                'paid_at' => $data['paid_at'] ?? null,
                'amount'  => (int) $data['amount'],
                'ref_no'  => $data['ref_no'] ?? null,
            ]);

            // Cập nhật trạng thái invoice
            $paid = (int) $invoice->payments()->sum('amount');
            $total = (int) $invoice->total;
            $status = $paid <= 0 ? 'unpaid' : ($paid < $total ? 'partial' : 'paid');
            $invoice->update(['status' => $status]);

            // Ghi log
            activity_log()->log(
                actorId: $request->user()?->id,
                action: 'invoice.paid',
                target: $invoice,
                meta: [
                    'payment_id' => $payment->id,
                    'method' => $payment->method,
                    'amount' => (int) $payment->amount,
                    'paid_at' => (string) $payment->paid_at,
                    'ref_no' => $payment->ref_no,
                ]
            );

            // Ghi nhận vào sổ cái
            \App\Services\StudentLedger::credit([
                'student_id' => $payment->invoice->student_id,
                'entry_date' => $payment->paid_at ? Carbon::parse($payment->paid_at)->toDateString() : now()->toDateString(),
                'type'       => 'payment',
                'ref_type'   => 'payments',
                'ref_id'     => $payment->id,
                'amount'     => (float) $payment->amount,
                'note'       => 'Payment #'.$payment->id,
                'meta'       => [
                    'invoice_code' => $invoice->code,
                    'method' => $payment->method ?? null,
                ],
            ]);
        });

        return back()->with('success', 'Đã ghi nhận thanh toán và cập nhật sổ cái.');
    }

    public function destroy(Invoice $invoice, Payment $payment)
    {
        $payment->delete();

        $paid = (int) $invoice->payments()->sum('amount');
        $total = (int) $invoice->total;
        $status = $paid <= 0 ? 'unpaid' : ($paid < $total ? 'partial' : 'paid');
        $invoice->update(['status' => $status]);

        return back()->with('success', 'Đã xoá khoản thanh toán.');
    }
}
