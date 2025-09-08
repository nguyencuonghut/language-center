<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Classroom;
use App\Services\InvoiceCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Builder;

class InvoiceController extends Controller
{
    /**
     * Danh sách hoá đơn (lọc theo chi nhánh, trạng thái, tìm kiếm, phân trang)
     */
    public function index(Request $request)
    {
        $query = Invoice::query()
            ->with([
                'student:id,code,name',
                'classroom:id,code,name',
                'branch:id,name',
            ]);

        // Filters
        $branch   = $request->string('branch')->toString();             // 'all' | branch_id
        $status   = $request->string('status')->toString();             // unpaid|partial|paid|refunded
        $q        = trim((string) $request->string('q'));
        $total    = $request->input('total');
        $dueDate  = $request->input('due_date');
        $perPage  = (int) $request->integer('per_page', 12);
        $sort     = $request->string('sort')->toString();               // created_at|due_date|total|status
        $order    = strtolower($request->string('order')->toString());  // asc|desc

        // Chi nhánh: mặc định = tất cả; nếu bạn muốn branch-scoping nghiêm ngặt,
        // có thể dùng active_branch_id ở đây.
        if ($branch && $branch !== 'all') {
            $query->where('branch_id', (int) $branch);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                // Search by exact invoice code or ID
                $w->where('code', 'like', "%{$q}%")
                  ->orWhere('id', $q);

                // Search by student code or classroom code within the invoice code
                $w->orWhereHas('student', function (Builder $ws) use ($q) {
                    $ws->where('name', 'like', "%{$q}%")
                       ->orWhere('code', 'like', "%{$q}%")
                       ->orWhere('phone', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%");
                });

                $w->orWhereHas('classroom', function (Builder $wc) use ($q) {
                    $wc->where('name', 'like', "%{$q}%")
                       ->orWhere('code', 'like', "%{$q}%");
                });

                // Search for student or class code in the invoice code
                $w->orWhere('code', 'like', "%-{$q}%"); // Matches "-{code}" in the invoice code
                $w->orWhere('code', 'like', "%{$q}-%"); // Matches "{code}-" in the invoice code
            });
        }

        // Search by total amount (exact match)
        if ($total) {
            $query->where('total', (int) $total);
        }

        // Search by due date (exact match)
        if ($dueDate) {
            $query->whereDate('due_date', $dueDate);
        }

        // Sorting
        $sortable = ['created_at','due_date','total','status','id'];
        if (!in_array($sort, $sortable, true)) {
            $sort = 'created_at';
        }
        if (!in_array($order, ['asc','desc'], true)) {
            $order = 'desc';
        }
        $query->orderBy($sort, $order);

        $invoices = $query->paginate($perPage)->withQueryString();

        // Phục vụ filter UI
        $branches = Branch::query()->select('id','name')->orderBy('name')->get();

        return Inertia::render('Manager/Invoices/Index', [
            'invoices' => $invoices,
            'branches' => $branches,
            'filters'  => [
                'branch'   => $branch ?: 'all',
                'status'   => $status ?: null,
                'q'        => $q ?: null,
                'total'    => $total ?: null,
                'due_date' => $dueDate ?: null,
                'perPage'  => $perPage,
                'sort'     => $sort,
                'order'    => $order,
            ],
        ]);
    }

    /**
     * Form tạo hoá đơn
     */
    public function create(Request $request, InvoiceCalculator $calc)
    {
        // Dữ liệu chọn
        $branches = Branch::select('id','name')->orderBy('name')->get();
        $students = Student::select('id','code','name','phone','email')->orderBy('name')->limit(50)->get();
        $classes  = Classroom::select('id','code','name')->orderBy('name')->limit(50)->get();

        $classId   = $request->integer('class_id');
        $studentId = $request->integer('student_id');

        $class     = $classId ? Classroom::find($classId) : null;
        $student   = $studentId ? Student::find($studentId) : null;

        $totalDefault = 0;
        if ($class && $student) {
            $totalDefault = $calc->tuitionDefaultTotal($class->id, $student->id, null);
        }

        return Inertia::render('Manager/Invoices/Create', [
            'branches' => $branches,
            'students' => $students,
            'classes'  => $classes,
            'defaults' => [
                'status' => 'unpaid',
                'total_defaulti'  => $totalDefault,
                'class_id'      => $class?->id,
                'student_id'    => $student?->id,
            ],
        ]);
    }

    /**
     * Lưu hoá đơn mới
     */
    public function store(StoreInvoiceRequest $request)
    {
        $data = $request->validated();

        $classroom = Classroom::find($data['class_id']);
        $student   = Student::find($data['student_id']);
        $invoice = Invoice::create([
            'code'      => 'INV-' . $student->code . '-' . $classroom->code,
            'branch_id' => $data['branch_id'],
            'student_id'=> $data['student_id'],
            'class_id'  => $data['class_id'] ?? null,
            'total'     => (int) $data['total'],
            'status'    => $data['status'] ?? 'unpaid',
            'due_date'  => $data['due_date'] ?? null,
        ]);

        return redirect()
            ->route('manager.invoices.show', $invoice->id)
            ->with('success', 'Đã tạo hoá đơn thành công.');
    }

    /**
     * Chi tiết hoá đơn
     */
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'student:id,code,name,phone,email',
            'classroom:id,code,name',
            'branch:id,name',
            'invoiceItems:id,invoice_id,type,description,qty,unit_price,amount,created_at',
            'payments:id,invoice_id,method,paid_at,amount,ref_no',
        ]);

        return Inertia::render('Manager/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Invoice $invoice)
    {
        return Inertia::render('Manager/Invoices/Edit', [
            'invoice'  => $invoice->load('student:id,code,name', 'classroom:id,code,name', 'branch:id,name'),
            'branches' => Branch::select('id','name')->orderBy('name')->get(),
            'students' => Student::select('id','code','name')->orderBy('name')->limit(50)->get(),
            'classrooms'  => Classroom::select('id','code','name')->orderBy('name')->limit(50)->get(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $data = $request->validated();

        $invoice->update([
            'branch_id'  => $data['branch_id'],
            'student_id' => $data['student_id'],
            'class_id'   => $data['class_id'] ?? null,
            'total'      => (int) $data['total'],
            'status'      => $data['status'] ?? $invoice->status,
            'due_date'   => $data['due_date'] ?? null,
        ]);

        return redirect()->route('manager.invoices.show', $invoice)->with('success', 'Đã cập nhật hoá đơn.');
    }

    /**
     * Xoá hoá đơn (chỉ khi chưa có thanh toán)
     */
    public function destroy(Invoice $invoice)
    {
        if ($invoice->payments()->exists()) {
            return back()->with('error', 'Không thể xoá hoá đơn đã có thanh toán.');
        }

        $invoice->invoiceItems()->delete();
        $invoice->delete();

        return redirect()->route('manager.invoices.index')->with('success', 'Đã xoá hoá đơn.');
    }
}
