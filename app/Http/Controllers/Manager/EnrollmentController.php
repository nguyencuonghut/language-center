<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    public function index(Request $request, Classroom $classroom)
    {
        $perPage = (int) $request->input('per_page', 12);
        $q       = trim((string) $request->input('q', ''));

        $enrollments = Enrollment::query()
            ->with(['student:id,code,name,phone,email'])
            ->where('class_id', $classroom->id)
            ->when($q !== '', function($qr) use ($q) {
                $qr->whereHas('student', function($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%")
                      ->orWhere('phone','like', "%{$q}%")
                      ->orWhere('email','like', "%{$q}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Gợi ý học viên (có thể thay bằng API search riêng)
        $suggestStudents = Student::query()
            ->select('id','code','name')
            ->orderBy('id','desc')
            ->limit(20)
            ->get()
            ->map(fn($s)=>[
                'id'=>$s->id,
                'label'=>"{$s->code} - {$s->name}",
                'value'=>(string)$s->id,
            ]);

        return Inertia::render('Manager/Classrooms/Enrollments/Index', [
            'classroom' => [
                'id' => $classroom->id,
                'code' => $classroom->code,
                'name' => $classroom->name,
                'branch_id' => $classroom->branch_id,
            ],
            'enrollments' => $enrollments,
            'suggestStudents' => $suggestStudents,
            'filters' => [
                'q' => $q,
                'perPage' => $perPage,
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function store(EnrollmentRequest $request, Classroom $classroom)
    {
        $data = $request->validated();

        // Chống ghi danh trùng
        $exists = Enrollment::where('class_id', $classroom->id)
            ->where('student_id', $data['student_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Học viên đã ghi danh vào lớp này.');
        }

        Enrollment::create([
            'class_id'          => $classroom->id,
            'student_id'        => $data['student_id'],
            'enrolled_at'       => $data['enrolled_at'] ?? now()->toDateString(),
            'start_session_no'  => $data['start_session_no'] ?? 1,
            'status'            => $data['status'] ?? 'active',
        ]);

        return back()->with('success', 'Đã ghi danh học viên vào lớp.');
    }

    public function destroy(Classroom $classroom, Enrollment $enrollment)
    {
        if ((int)$enrollment->class_id !== (int)$classroom->id) {
            return back()->with('error','Bản ghi không thuộc lớp này.');
        }

        $enrollment->delete();

        return back()->with('success','Đã huỷ ghi danh.');
    }

    // (tuỳ chọn) API tìm học viên
    public function searchStudents(Request $request, Classroom $classroom)
    {
        $q = trim((string) $request->input('q',''));
        $limit = min(30, max(5, (int) $request->input('limit', 20)));

        $students = Student::query()
            ->when($q !== '', function($qr) use ($q) {
                $qr->where('name','like',"%{$q}%")
                   ->orWhere('code','like',"%{$q}%")
                   ->orWhere('phone','like',"%{$q}%")
                   ->orWhere('email','like',"%{$q}%");
            })
            ->orderBy('id','desc')
            ->limit($limit)
            ->get(['id','code','name']);

        return response()->json($students->map(fn($s)=>[
            'id'=>$s->id,
            'label'=>"{$s->code} - {$s->name}",
            'value'=>(string)$s->id,
        ]));
    }

    public function bulkStore(\Illuminate\Http\Request $request, Classroom $classroom)
    {
        $data = $request->validate([
            'student_ids'       => ['required','array','min:1'],
            'student_ids.*'     => ['integer','exists:students,id'],
            'enrolled_at'       => ['nullable','date'],
            'start_session_no'  => ['nullable','integer','min:1'],
            'status'            => ['nullable', Rule::in(['active','transferred','completed','dropped'])],
        ], [
            'student_ids.required'  => 'Vui lòng chọn ít nhất 1 học viên.',
            'student_ids.array'     => 'Dữ liệu học viên không hợp lệ.',
            'student_ids.*.exists'  => 'Có học viên không tồn tại.',
            'enrolled_at.date'      => 'Ngày ghi danh không đúng định dạng.',
            'start_session_no.min'  => '“Bắt đầu từ buổi số” tối thiểu là 1.',
            'status.in'             => 'Trạng thái không hợp lệ.',
        ]);

        // Chuẩn hoá: ép tất cả id thành int + loại trùng trên payload
        $ids = collect($data['student_ids'])
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values();

        $defaultEnrolledAt = $data['enrolled_at'] ?? now()->toDateString();
        $startNo = (int) ($data['start_session_no'] ?? 1);
        $status  = $data['status'] ?? 'active';

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($ids, $classroom, $defaultEnrolledAt, $startNo, $status, &$created, &$skipped) {
            // Lấy trước những student_id đã tồn tại (ép về int để so strict)
            $existingIds = Enrollment::where('class_id', $classroom->id)
                ->whereIn('student_id', $ids)
                ->pluck('student_id')
                ->map(fn($v) => (int) $v)
                ->all();

            // Loại bỏ những id đã có (so sánh strict an toàn vì cùng là int)
            $toCreate = $ids->reject(fn($id) => in_array($id, $existingIds, true))->values();

            // Tạo các bản ghi mới
            foreach ($toCreate as $sid) {
                $model = Enrollment::firstOrCreate(
                    ['class_id' => $classroom->id, 'student_id' => $sid],
                    [
                        'enrolled_at'      => $defaultEnrolledAt,
                        'start_session_no' => $startNo,
                        'status'           => $status,
                    ]
                );
                if ($model->wasRecentlyCreated) $created++;
            }

            // Số bị bỏ qua = trùng trong payload + đã tồn tại trong DB
            $skipped = $ids->count() - $toCreate->count();
        });

        if ($created > 0 && $skipped > 0) {
            return back()->with('success', "Đã ghi danh {$created} học viên; bỏ qua {$skipped} học viên đã tồn tại.");
        } elseif ($created > 0) {
            return back()->with('success', "Đã ghi danh {$created} học viên.");
        } else {
            return back()->with('success', "Tất cả học viên đã có trong lớp, không có bản ghi nào được thêm.");
        }
    }

}
