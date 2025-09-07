<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Http\Requests\Courses\StoreCourseRequest;
use App\Http\Requests\Courses\UpdateCourseRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CourseController extends Controller
{
    /**
     * Danh sách khoá học (lọc + sắp xếp + phân trang)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $q        = trim((string) $request->input('q', ''));
        $language = $request->input('language');
        $perPage  = (int) $request->input('per_page', 12);
        $perPage  = $perPage > 0 ? min($perPage, 100) : 12;

        $sort     = $request->input('sort');
        $order    = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['code', 'name', 'audience', 'language', 'active', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = null;
        }

        $query = Course::query();

        // Branch scoping for managers - Courses are global but we can add filtering later if needed
        // For now, all users can see all courses since they are educational content
        // Future enhancement: could add branch-specific course customization

        // Apply search if query exists
        $query->search($q);

        // Apply language filter
        if ($language && $language !== 'all') {
            $query->where('language', $language);
        }

        if ($sort) {
            $query->orderBy($sort, $order);
        } else {
            $query->latest('id');
        }

        $courses = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Manager/Courses/Index', [
            'courses' => $courses,
            'filters' => [
                'q'        => $q,
                'language' => $language,
                'perPage'  => $perPage,
                'sort'     => $sort,
                'order'    => $order,
            ],
        ]);
    }

    /**
     * Form tạo
     */
    public function create()
    {
        return Inertia::render('Manager/Courses/Create', [
            'defaults' => [
                'active' => true,
            ],
        ]);
    }

    /**
     * Lưu khoá học mới
     */
    public function store(StoreCourseRequest $request)
    {
        $data = $request->validated();
        $data['active'] = (bool) ($data['active'] ?? false);

        $course = Course::create($data);

        return redirect()
            ->route('manager.courses.show', $course->id)
            ->with('success', 'Đã tạo khoá học thành công.');
    }

    /**
     * Chi tiết
     */
    public function show(Course $course)
    {
        return Inertia::render('Manager/Courses/Show', [
            'course' => $course,
        ]);
    }

    /**
     * Form sửa
     */
    public function edit(Course $course)
    {
        return Inertia::render('Manager/Courses/Edit', [
            'course' => $course,
        ]);
    }

    /**
     * Cập nhật
     */
    public function update(UpdateCourseRequest $request, Course $course)
    {
        $data = $request->validated();
        $data['active'] = (bool) ($data['active'] ?? false);

        $course->update($data);

        return redirect()
            ->route('manager.courses.show', $course->id)
            ->with('success', 'Đã cập nhật khoá học.');
    }

    /**
     * Xoá
     */
    public function destroy(Course $course)
    {
        try {
            // Check if course is being used by any classrooms
            if ($course->classrooms()->exists()) {
                return back()->with('error', 'Không thể xoá khoá học vì đang có lớp học sử dụng.');
            }

            $course->delete();
            return redirect()
                ->route('manager.courses.index')
                ->with('success', 'Đã xoá khoá học.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể xoá khoá học (có thể đang được sử dụng).');
        }
    }
}
