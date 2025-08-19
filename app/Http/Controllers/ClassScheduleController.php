<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassScheduleRequest;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassScheduleController extends Controller
{
    /**
     * Danh sách lịch theo lớp (nested: classrooms/{classroom}/schedules)
     */
    public function index(Classroom $classroom, Request $request)
    {
        // sort + order + per_page theo style Room
        $allowedSorts = ['weekday', 'start_time', 'end_time'];
        $sort  = in_array($request->query('sort'), $allowedSorts) ? $request->query('sort') : 'weekday';
        $order = $request->query('order') === 'desc' ? 'desc' : 'asc';

        $perPage = (int) $request->query('per_page', 12);
        if ($perPage < 5)  $perPage = 5;
        if ($perPage > 50) $perPage = 50;

        $schedules = ClassSchedule::query()
            ->where('class_id', $classroom->id)
            ->orderBy($sort, $order)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Classrooms/Schedules/Index', [
            'classroom' => $classroom->only('id', 'code', 'name', 'branch_id'),
            'schedules' => $schedules,
            'filters'   => [
                'sort'    => $sort,
                'order'   => $order,
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Form tạo lịch cho lớp
     */
    public function create(Classroom $classroom)
    {
        return Inertia::render('Admin/Classrooms/Schedules/Create', [
            'classroom' => $classroom->only('id', 'code', 'name', 'branch_id'),
        ]);
    }

    /**
     * Lưu lịch mới
     */
    public function store(ClassScheduleRequest $request, Classroom $classroom)
    {
        // ClassScheduleRequest đã merge class_id từ route
        ClassSchedule::create($request->validated());

        return redirect()
            ->route('admin.classrooms.schedules.index', $classroom->id)
            ->with('success', 'Đã thêm lịch cho lớp.');
    }

    /**
     * Form sửa lịch
     */
    public function edit(Classroom $classroom, ClassSchedule $schedule)
    {
        // Đảm bảo lịch thuộc đúng lớp (an toàn thêm dù đã nested)
        if ($schedule->class_id !== $classroom->id) {
            abort(404);
        }

        return Inertia::render('Admin/Classrooms/Schedules/Edit', [
            'classroom' => $classroom->only('id', 'code', 'name', 'branch_id'),
            'schedule'  => $schedule->only('id', 'class_id', 'weekday', 'start_time', 'end_time'),
        ]);
    }

    /**
     * Cập nhật lịch
     */
    public function update(ClassScheduleRequest $request, Classroom $classroom, ClassSchedule $schedule)
    {
        if ($schedule->class_id !== $classroom->id) {
            abort(404);
        }

        $schedule->update($request->validated());

        return redirect()
            ->route('admin.classrooms.schedules.index', $classroom->id)
            ->with('success', 'Đã cập nhật lịch.');
    }

    /**
     * Xoá lịch
     */
    public function destroy(Classroom $classroom, ClassSchedule $schedule)
    {
        if ($schedule->class_id !== $classroom->id) {
            abort(404);
        }

        $schedule->delete();

        return redirect()
            ->route('admin.classrooms.schedules.index', $classroom->id)
            ->with('success', 'Đã xoá lịch.');
    }
}
