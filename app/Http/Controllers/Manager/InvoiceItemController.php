<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceItemRequest;
use App\Http\Requests\Invoices\UpdateInvoiceItemRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

class InvoiceItemController extends Controller
{
    public function store(StoreInvoiceItemRequest $request, Invoice $invoice)
    {
        $data = $request->validated();

        DB::transaction(function () use ($invoice, $data) {
            // Tính amount: nếu không truyền, tự tính từ qty*unit_price
            // Với các loại "refund", "transfer_out" → mặc định âm
            $type = $data['type'];
            $sign = in_array($type, ['refund','transfer_out']) ? -1 : 1;

            $amount = array_key_exists('amount', $data) && $data['amount'] !== null
                ? (int) $data['amount']
                : $sign * ((int)$data['qty']) * ((int)$data['unit_price']);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'type'        => $type,
                'description' => $data['description'] ?? null,
                'qty'         => (int) $data['qty'],
                'unit_price'  => (int) $data['unit_price'],
                'amount'      => (int) $amount,
            ]);

            $this->recalcInvoiceTotal($invoice);
        });

        return back()->with('success', 'Đã thêm dòng hoá đơn.');
    }

    public function update(UpdateInvoiceItemRequest $request, Invoice $invoice, InvoiceItem $item)
    {
        if ($item->invoice_id !== $invoice->id) {
            abort(404);
        }

        $data = $request->validated();

        DB::transaction(function () use ($invoice, $item, $data) {
            // Hợp nhất dữ liệu hiện tại để tính amount nếu cần
            $payload = [
                'type'        => $data['type']        ?? $item->type,
                'description' => array_key_exists('description',$data) ? $data['description'] : $item->description,
                'qty'         => (int) ($data['qty'] ?? $item->qty),
                'unit_price'  => (int) ($data['unit_price'] ?? $item->unit_price),
            ];

            // amount: nếu gửi lên (kể cả 0) → dùng; nếu null → tự tính lại
            if (array_key_exists('amount', $data)) {
                $payload['amount'] = (int) $data['amount'];
            } else {
                $sign = in_array($payload['type'], ['refund','transfer_out']) ? -1 : 1;
                $payload['amount'] = $sign * $payload['qty'] * $payload['unit_price'];
            }

            $item->update($payload);

            $this->recalcInvoiceTotal($invoice);
        });

        return back()->with('success', 'Đã cập nhật dòng hoá đơn.');
    }

    public function destroy(Invoice $invoice, InvoiceItem $item)
    {
        if ($item->invoice_id !== $invoice->id) {
            abort(404);
        }

        DB::transaction(function () use ($invoice, $item) {
            $item->delete();
            $this->recalcInvoiceTotal($invoice);
        });

        return back()->with('success', 'Đã xoá dòng hoá đơn.');
    }

    private function recalcInvoiceTotal(Invoice $invoice): void
    {
        $sum = (int) $invoice->invoiceItems()->sum('amount');
        $invoice->update(['total' => $sum]);

        // Sau khi total thay đổi, có thể cập nhật trạng thái invoice theo payments (nếu muốn đồng bộ ngay)
        $paid  = (int) $invoice->payments()->sum('amount');
        $status = 'unpaid';
        if ($paid <= 0) {
            $status = 'unpaid';
        } elseif ($paid > 0 && $paid < $sum) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }
        $invoice->update(['status' => $status]);
    }
}
