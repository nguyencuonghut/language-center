<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\RevertTransferRequest;
use App\Http\Requests\Transfer\RetargetTransferRequest;
use App\Http\Requests\Students\StoreTransferRequest;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TransferController extends Controller
{
    protected TransferService $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Display a listing of transfers.
     */
    public function index(Request $request)
    {
        $query = Transfer::with(['student:id,code,name', 'fromClass:id,code,name', 'toClass:id,code,name', 'retargetedToClass:id,code,name', 'createdBy:id,name'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('status') && $request->get('status') !== '' && $request->get('status') !== 'Tất cả') {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('from_date')) {
            $fromDate = \Carbon\Carbon::parse($request->from_date)->startOfDay();
            $query->where('created_at', '>=', $fromDate);
        }

        if ($request->filled('to_date')) {
            $toDate = \Carbon\Carbon::parse($request->to_date)->endOfDay();
            $query->where('created_at', '<=', $toDate);
        }

        if ($request->filled('q')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->q}%")
                  ->orWhere('code', 'like', "%{$request->q}%");
            });
        }

        $transfers = $query->paginate(10)->withQueryString();

        // Stats
        $stats = $this->transferService->getTransferStats([
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
        ]);

        return Inertia::render('Manager/Transfers/Index', [
            'transfers' => $transfers,
            'stats' => $stats,
            'filters' => $request->only(['status', 'student_id', 'from_date', 'to_date', 'q']),
        ]);
    }

    /**
     * Show the form for creating a new transfer.
     */
    public function create(Request $request)
    {
        $student = null;
        if ($request->filled('student_id')) {
            $student = Student::with(['enrollments.classroom'])->find($request->student_id);
        }

        $classrooms = Classroom::with('branch:id,name')
            ->whereIn('status', ['active', 'open'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'branch_id']);

        return Inertia::render('Manager/Transfers/Create', [
            'student' => $student,
            'classrooms' => $classrooms,
        ]);
    }

    /**
     * Store a newly created transfer in storage.
     */
    public function store(StoreTransferRequest $request)
    {
        try {
            $transfer = $this->transferService->createTransfer($request->validated());

            return redirect()->route('manager.transfers.index')
                ->with('success', 'Chuyển lớp thành công!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified transfer.
     */
    public function show(Transfer $transfer)
    {
        $transfer->load([
            'student:id,code,name,email,phone',
            'fromClass:id,code,name',
            'toClass:id,code,name',
            'retargetedToClass:id,code,name',
            'createdBy:id,name',
            'revertedBy:id,name',
            'retargetedBy:id,name',
            'invoice:id,code,total,status',
        ]);

        return Inertia::render('Manager/Transfers/Show', [
            'transfer' => $transfer,
        ]);
    }

    /**
     * Create transfer for specific student (legacy support)
     */
    public function createForStudent(Student $student)
    {
        return redirect()->route('manager.transfers.create', ['student_id' => $student->id]);
    }

    /**
     * Store transfer for specific student (legacy support)
     */
    public function storeForStudent(StoreTransferRequest $request, Student $student)
    {
        try {
            $data = $request->validated();
            $data['student_id'] = $student->id;

            $transfer = $this->transferService->createTransfer($data);

            return back()->with('success', 'Chuyển lớp thành công!');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
    /** Hoàn tác chuyển lớp: quay lại lớp cũ, xoá enrollment lớp đích nếu chưa phát sinh dữ liệu; xoá hoá đơn chuyển (nếu còn unpaid). */
    public function revert(RevertTransferRequest $request)
    {
        $data = $request->validated();

        // Tìm transfer record
        $transfer = Transfer::active()
            ->where('student_id', $data['student_id'])
            ->where('from_class_id', $data['from_class_id'])
            ->where('to_class_id', $data['to_class_id'])
            ->first();

        if (!$transfer) {
            return back()->with('error', 'Không tìm thấy transfer record để hoàn tác.');
        }

        try {
            $this->transferService->revertTransfer($transfer);
            return back()->with('success', 'Đã hoàn tác chuyển lớp thành công.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Sửa hướng chuyển lớp: bỏ lớp đích cũ → chuyển sang lớp đích mới. */
    public function retarget(RetargetTransferRequest $request)
    {
        $data = $request->validated();

        // Tìm transfer record
        $transfer = Transfer::active()
            ->where('student_id', $data['student_id'])
            ->where('from_class_id', $data['from_class_id'])
            ->where('to_class_id', $data['old_to_class_id'])
            ->first();

        if (!$transfer) {
            return back()->with('error', 'Không tìm thấy transfer record để retarget.');
        }

        try {
            $newTargetClass = Classroom::findOrFail($data['new_to_class_id']);
            $this->transferService->retargetTransfer($transfer, $newTargetClass, $data);

            return back()->with('success', 'Đã cập nhật chuyển lớp sang lớp mới.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
