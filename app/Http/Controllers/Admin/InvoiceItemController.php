<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceItemRequest;
use App\Http\Requests\Invoices\UpdateInvoiceItemRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;

class InvoiceItemController extends Controller
{
    public function store(StoreInvoiceItemRequest $request, Invoice $invoice)
    {
        $data = $request->validated();

        $item = $invoice->items()->create([
            'type'        => $data['type'],
            'description' => $data['description'] ?? null,
            'qty'         => (int) ($data['qty'] ?? 1),
            'unit_price'  => (int) ($data['unit_price'] ?? 0),
            'amount'      => (int) $data['amount'],
        ]);

        // cập nhật tổng
        $invoice->update(['total' => (int) $invoice->items()->sum('amount')]);

        return back()->with('success', 'Đã thêm dòng hoá đơn.');
    }

    public function update(UpdateInvoiceItemRequest $request, Invoice $invoice, InvoiceItem $item)
    {
        $data = $request->validated();

        $item->update([
            'type'        => $data['type'],
            'description' => $data['description'] ?? null,
            'qty'         => (int) ($data['qty'] ?? 1),
            'unit_price'  => (int) ($data['unit_price'] ?? 0),
            'amount'      => (int) $data['amount'],
        ]);

        $invoice->update(['total' => (int) $invoice->items()->sum('amount')]);

        return back()->with('success', 'Đã cập nhật dòng hoá đơn.');
    }

    public function destroy(Invoice $invoice, InvoiceItem $item)
    {
        $item->delete();
        $invoice->update(['total' => (int) $invoice->items()->sum('amount')]);

        return back()->with('success', 'Đã xoá dòng hoá đơn.');
    }
}
