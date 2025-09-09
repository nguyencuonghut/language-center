<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\HolidayRequest;
use App\Models\Holiday;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
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

        // Branch scoping for managers
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            $query->where(function ($q) use ($userBranchIds) {
                $q->where('scope', 'global')
                  ->orWhere(function ($q2) use ($userBranchIds) {
                      $q2->where('scope', 'branch')->whereIn('branch_id', $userBranchIds);
                  });
            });
        }

        return inertia('Admin/Holidays/Index', [
            'holidays' => $query->paginate($perPage)->withQueryString(),
            'filters'  => [
                'scope'     => $scope ?: 'all',
                'branch_id' => $branchId ?: null,
                'class_id'  => $classId ?: null,
                'perPage'   => $perPage,
            ],
            // options cho dropdown
            'branches'  => Branch::query()
                ->when($user->hasRole('manager'), function ($query) use ($user) {
                    $userBranchIds = $user->managerBranches()->pluck('branches.id');
                    $query->whereIn('id', $userBranchIds);
                })
                ->orderBy('name')->get(['id','name']),
            'classes'   => Classroom::query()
                ->when($user->hasRole('manager'), function ($query) use ($user) {
                    $userBranchIds = $user->managerBranches()->pluck('branches.id');
                    $query->whereIn('branch_id', $userBranchIds);
                })
                ->orderBy('name')->get(['id','code','name']),
        ]);
    }

    public function create()
    {
        /** @var User $user */
        $user = Auth::user();

        $branches = Branch::where('active', 1)
            ->when($user->hasRole('manager'), function ($query) use ($user) {
                $userBranchIds = $user->managerBranches()->pluck('branches.id');
                $query->whereIn('id', $userBranchIds);
            })
            ->get(['id','name']);

        $classrooms = Classroom::query()
            ->when($user->hasRole('manager'), function ($query) use ($user) {
                $userBranchIds = $user->managerBranches()->pluck('branches.id');
                $query->whereIn('branch_id', $userBranchIds);
            })
            ->orderBy('name')
            ->get(['id','code','name']);

        return Inertia::render('Admin/Holidays/Create', [
            'branches' => $branches,
            'classrooms' => $classrooms,
        ]);
    }

    public function store(HolidayRequest $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validated();

        // Check if manager has access to the selected branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if ($data['scope'] === 'branch' && !in_array($data['branch_id'], $userBranchIds->toArray())) {
                return back()->withErrors([
                    'branch_id' => 'Bạn không có quyền tạo ngày nghỉ cho chi nhánh này.'
                ]);
            }
        }

        Holiday::create($data);
        return redirect()->route('admin.holidays.index')->with('success', 'Đã thêm ngày nghỉ');
    }

    public function edit(Holiday $holiday)
    {
        /** @var User $user */
        $user = Auth::user();

        // Check if manager has access to this holiday's branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if ($holiday->scope === 'branch' && !in_array($holiday->branch_id, $userBranchIds->toArray())) {
                abort(403, 'Bạn không có quyền chỉnh sửa ngày nghỉ này.');
            }
        }

        $branches = Branch::where('active', 1)
            ->when($user->hasRole('manager'), function ($query) use ($user) {
                $userBranchIds = $user->managerBranches()->pluck('branches.id');
                $query->whereIn('id', $userBranchIds);
            })
            ->get(['id','name']);

        $classrooms = Classroom::query()
            ->when($user->hasRole('manager'), function ($query) use ($user) {
                $userBranchIds = $user->managerBranches()->pluck('branches.id');
                $query->whereIn('branch_id', $userBranchIds);
            })
            ->orderBy('name')
            ->get(['id','code','name']);

        return Inertia::render('Admin/Holidays/Edit', [
            'holiday' => $holiday,
            'branches' => $branches,
            'classrooms' => $classrooms,
        ]);
    }

    public function update(HolidayRequest $request, Holiday $holiday)
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validated();

        // Check if manager has access to both current and new branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if ($holiday->scope === 'branch' && !in_array($holiday->branch_id, $userBranchIds->toArray())) {
                return back()->withErrors([
                    'branch_id' => 'Bạn không có quyền cập nhật ngày nghỉ này.'
                ]);
            }
            if ($data['scope'] === 'branch' && !in_array($data['branch_id'], $userBranchIds->toArray())) {
                return back()->withErrors([
                    'branch_id' => 'Bạn không có quyền chuyển ngày nghỉ sang chi nhánh này.'
                ]);
            }
        }

        $holiday->update($data);
        return redirect()->route('admin.holidays.index')->with('success', 'Đã cập nhật ngày nghỉ');
    }

    public function destroy(Holiday $holiday)
    {
        /** @var User $user */
        $user = Auth::user();

        // Check if manager has access to this holiday's branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if ($holiday->scope === 'branch' && !in_array($holiday->branch_id, $userBranchIds->toArray())) {
                return back()->with('error', 'Bạn không có quyền xóa ngày nghỉ này.');
            }
        }

        // Check if holiday affects any class sessions
        $affectedSessions = \App\Models\ClassSession::query()
            ->where('date', '>=', $holiday->start_date)
            ->where('date', '<=', $holiday->end_date)
            ->when($holiday->scope === 'branch', fn($q) => $q->whereHas('classroom', fn($sq) => $sq->where('branch_id', $holiday->branch_id)))
            ->when($holiday->scope === 'class', fn($q) => $q->where('class_id', $holiday->class_id))
            ->count();

        if ($affectedSessions > 0) {
            return back()->with('error', "Không thể xóa ngày nghỉ vì đang ảnh hưởng đến {$affectedSessions} buổi học.");
        }

        $holiday->delete();
        return back()->with('success', 'Đã xóa ngày nghỉ');
    }
}
