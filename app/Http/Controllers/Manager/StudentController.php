<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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
