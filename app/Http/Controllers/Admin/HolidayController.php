<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Branch;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $scope     = $request->string('scope')->toString(); // '', 'global','branch','class'
        $branchId  = $request->integer('branch_id');
        $classId   = $request->integer('class_id');
        $perPage   = $request->integer('per_page') ?: 20;

        $query = Holiday::query()
            ->with(['branch:id,name', 'classroom:id,code,name'])
            ->when(in_array($scope, ['global','branch','class'], true), fn($q) => $q->where('scope', $scope))
            ->when($scope === 'branch' && $branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($scope === 'class'  && $classId,  fn($q) => $q->where('class_id',  $classId))
            ->orderByDesc('start_date');

        return inertia('Admin/Holidays/Index', [
            'holidays' => $query->paginate($perPage)->withQueryString(),
            'filters'  => [
                'scope'     => $scope ?: 'all',
                'branch_id' => $branchId ?: null,
                'class_id'  => $classId ?: null,
                'perPage'   => $perPage,
            ],
            // options cho dropdown
            'branches'  => Branch::query()->orderBy('name')->get(['id','name']),
            'classes'   => Classroom::query()->orderBy('name')->get(['id','code','name']),
        ]);
    }

    public function create()
    {
        $branches = Branch::where('active', 1)->get(['id','name']);
        return Inertia::render('Admin/Holidays/Create', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'is_closed' => ['boolean'],
            'repeats_annually' => ['boolean'],
            'scope' => ['required', Rule::in(['global','branch'])],
        ]);
        Holiday::create($data);
        return redirect()->route('admin.holidays.index')->with('success', 'Đã thêm ngày nghỉ');
    }

    public function edit(Holiday $holiday)
    {
        $branches = Branch::where('active', 1)->get(['id','name']);
        return Inertia::render('Admin/Holidays/Edit', [
            'holiday' => $holiday,
            'branches' => $branches,
        ]);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'is_closed' => ['boolean'],
            'repeats_annually' => ['boolean'],
            'scope' => ['required', Rule::in(['global','branch'])],
        ]);
        $holiday->update($data);
        return redirect()->route('admin.holidays.index')->with('success', 'Đã cập nhật ngày nghỉ');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect()->route('admin.holidays.index')->with('success', 'Đã xóa ngày nghỉ');
    }
}
