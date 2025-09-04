<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Invoice;
use App\Models\Attendance;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int)($request->integer('per_page') ?: 20);
        $sort    = $request->input('sort', 'id');
        $order   = $request->input('order', 'desc');
        $q       = trim((string)$request->input('q', ''));

        $query = Student::query();

        if ($q !== '') {
            $query->where(function ($x) use ($q) {
                $x->where('code', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        // Chống SQL injection cho sort
        $allowSort = ['id','code','name','phone','email','active','created_at'];
        if (!in_array($sort, $allowSort, true)) $sort = 'id';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $students = $query->orderBy($sort, $order)->paginate($perPage)->withQueryString();

        return Inertia::render('Manager/Students/Index', [
            'students' => $students,
            'filters'  => [
                'q'        => $q,
                'perPage'  => $perPage,
                'sort'     => $sort,
                'order'    => $order,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Manager/Students/Create', [
            'defaults' => [
                'active' => true,
            ],
            'genders' => [
                ['label' => 'Nam',  'value' => 'Nam'],
                ['label' => 'Nữ',   'value' => 'Nữ'],
                ['label' => 'Khác', 'value' => 'Khác'],
            ],
        ]);
    }

    public function store(StoreStudentRequest $request)
    {
        $data = $request->validated();

        // Chuẩn hoá nhẹ
        if (isset($data['phone'])) $data['phone'] = trim(preg_replace('/\s+/', '', $data['phone']));
        if (isset($data['email'])) $data['email'] = strtolower(trim($data['email']));

        Student::create($data);

        return to_route('manager.students.index')
            ->with('success', 'Đã tạo học viên thành công.');
    }

    public function edit(Student $student)
    {
        return Inertia::render('Manager/Students/Edit', [
            'student' => $student,
            'genders' => [
                ['label' => 'Nam',  'value' => 'Nam'],
                ['label' => 'Nữ',   'value' => 'Nữ'],
                ['label' => 'Khác', 'value' => 'Khác'],
            ],
        ]);
    }

    /**
     * Hiển thị trang 360° của học viên
     */
    public function show(Student $student)
    {
        // ENROLLMENTS: kèm code/name lớp để hiển thị nhanh
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->leftJoin('classrooms', 'enrollments.class_id', '=', 'classrooms.id') // nếu bảng là "classrooms", đổi lại cho khớp
            ->orderByDesc('enrolled_at')
            ->get([
                'enrollments.id',
                'enrollments.class_id',
                'enrollments.start_session_no',
                'enrollments.enrolled_at',
                'enrollments.status',
                DB::raw('classrooms.code as class_code'),
                DB::raw('classrooms.name as class_name'),
            ])
            ->map(function ($e) {
                return [
                    'id'                => (int) $e->id,
                    'class_id'          => (int) $e->class_id,
                    'class_code'        => $e->class_code,
                    'class_name'        => $e->class_name,
                    'start_session_no'  => (int) $e->start_session_no,
                    'enrolled_at'       => optional($e->enrolled_at)->toDateString(),
                    'status'            => $e->status,
                    // Add classroom object for compatibility with Create.vue
                    'classroom'         => [
                        'id'   => (int) $e->class_id,
                        'code' => $e->class_code,
                        'name' => $e->class_name,
                    ]
                ];
            });

        // If it's an AJAX request, return JSON (for TransferService)
        if (request()->expectsJson()) {
            return response()->json([
                'id'     => $student->id,
                'code'   => $student->code,
                'name'   => $student->name,
                'phone'  => $student->phone,
                'email'  => $student->email,
                'active' => (bool) $student->active,
                'enrollments' => $enrollments,
            ]);
        }

        // INVOICES: kèm items + payments + branch + classroom + student (nhẹ)
        // Lưu ý: Model Invoice nên có quan hệ classroom()->belongsTo(Classroom::class,'class_id')
        $invoices = Invoice::query()
            ->with([
                'invoiceItems:id,invoice_id,type,description,qty,unit_price,amount,created_at,updated_at',
                'payments:id,invoice_id,method,paid_at,amount,ref_no,created_at,updated_at',
                'branch:id,name',
                'classroom:id,code,name',   // nếu bạn dùng bảng "classes" → sửa lại quan hệ & fields cho khớp
                'student:id,code,name',
            ])
            ->where('student_id', $student->id)
            ->orderByDesc('id')
            ->get([
                'id','branch_id','student_id','class_id','total','status','due_date','created_at'
            ]);

        // ATTENDANCE SUMMARY: đếm theo status
        $attCounts = Attendance::query()
            ->where('student_id', $student->id)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $attendanceSummary = [
            'present' => (int) ($attCounts['present'] ?? 0),
            'absent'  => (int) ($attCounts['absent'] ?? 0),
            'late'    => (int) ($attCounts['late'] ?? 0),
            'excused' => (int) ($attCounts['excused'] ?? 0),
        ];

        return Inertia::render('Manager/Students/Show', [
            'student'            => [
                'id'     => $student->id,
                'code'   => $student->code,
                'name'   => $student->name,
                'phone'  => $student->phone,
                'email'  => $student->email,
                'active' => (bool) $student->active,
                'created_at' => optional($student->created_at)->toDateTimeString(),
            ],
            'enrollments'        => $enrollments,
            'invoices'           => $invoices,
            'attendanceSummary'  => $attendanceSummary,
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student)
    {
        $data = $request->validated();

        if (isset($data['phone'])) $data['phone'] = trim(preg_replace('/\s+/', '', $data['phone']));
        if (isset($data['email'])) $data['email'] = strtolower(trim($data['email']));

        $student->update($data);

        return redirect()->route('manager.students.index')->with('success', 'Đã cập nhật học viên.');
    }

    public function destroy(Student $student)
    {
        try {
            // Nếu có enrollment thì tuỳ nghiệp vụ: chặn xoá
            $hasEnroll = Enrollment::where('student_id', $student->id)->exists();
            if ($hasEnroll) {
                return back()->with('error', 'Không thể xoá học viên vì đã có ghi danh.');
            }

            $student->delete();

            return back()->with('success', 'Đã xoá học viên.');
        } catch (QueryException $e) {
            return back()->with('error', 'Không thể xoá học viên (ràng buộc dữ liệu).');
        }
    }

    // (tuỳ chọn) API: gợi ý tìm học viên
    public function search(Request $request)
    {
        $q = trim((string)$request->input('q', ''));

        $items = Student::query()
            ->when($q !== '', function ($x) use ($q) {
                $x->where('code','like',"%{$q}%")
                  ->orWhere('name','like',"%{$q}%")
                  ->orWhere('phone','like',"%{$q}%")
                  ->orWhere('email','like',"%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id','code','name','phone','email'])
            ->map(fn($s) => [
                'value' => $s->id,
                'label' => trim($s->code.' · '.$s->name.($s->phone ? " ({$s->phone})" : '')),
                'code'  => $s->code,
                'name'  => $s->name,
            ]);

        return response()->json($items);
    }
}
