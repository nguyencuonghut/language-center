<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StorePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Invoice $invoice)
    {
        $data = $request->validated();

        $payment = $invoice->payments()->create([
            'method'  => $data['method'],
            'paid_at' => $data['paid_at'] ?? null, // Y-m-d H:i:s hoặc Y-m-d tuỳ bạn
            'amount'  => (int) $data['amount'],
            'ref_no'  => $data['ref_no'] ?? null,
        ]);

        // tính trạng thái invoice
        $paid = (int) $invoice->payments()->sum('amount');
        $total = (int) $invoice->total;
        $status = $paid <= 0 ? 'unpaid' : ($paid < $total ? 'partial' : 'paid');
        $invoice->update(['status' => $status]);

        // cập nhật trạng thái invoice xong...
        activity_log()->log(
            actorId: $request->user()?->id,
            action: 'invoice.paid',
            target: $invoice,                       // target: Invoice
            meta: [
                'payment_id' => $payment->id,
                'method' => $payment->method,
                'amount' => (int) $payment->amount,
                'paid_at' => (string) $payment->paid_at,
                'ref_no' => $payment->ref_no,
            ]
        );

        return back()->with('success', 'Đã ghi nhận thanh toán.');
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
