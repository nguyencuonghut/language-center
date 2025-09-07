<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Models\Room;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Branch scoping for managers
        $userBranchIds = collect();
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
        }
        
        $branchParam = $request->query('branch'); // 'all' | null | <id>
        $q           = trim((string) $request->query('q', ''));
        $perPage     = (int) ($request->query('per_page', 12));
        $perPage     = $perPage > 0 && $perPage <= 100 ? $perPage : 12;

        // Sorting parameters
        $sort  = $request->query('sort', 'code');
        $order = strtolower($request->query('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Validate sort fields
        $allowedSorts = ['code', 'name', 'capacity', 'branch_id'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'code';

        $rooms = Room::query()
            // Apply branch scoping for managers
            ->when($user->hasRole('manager'), function ($query) use ($userBranchIds) {
                $query->whereIn('branch_id', $userBranchIds);
            })
            ->when($branchParam && $branchParam !== 'all', fn($qB) => $qB->where('branch_id', (int)$branchParam))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->with('branch:id,name')
            ->orderBy($sort, $order)
            ->orderBy('code') // Secondary sort
            ->paginate($perPage)
            ->withQueryString();

        // Get branches based on user role
        $branches = Branch::query()
            ->when($user->hasRole('manager'), function ($query) use ($userBranchIds) {
                $query->whereIn('id', $userBranchIds);
            })
            ->select('id','name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Manager/Rooms/Index', [
            'rooms'    => $rooms,
            'branches' => $branches,
            'filters'  => [
                'branch'  => $branchParam && $branchParam !== 'all' ? (string)(int)$branchParam : 'all',
                'q'       => $q,
                'perPage' => $perPage,
                'sort'    => $sort,
                'order'   => $order,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        
        // Get branches based on user role
        $branches = Branch::query()
            ->when($user->hasRole('manager'), function ($query) use ($user) {
                $userBranchIds = $user->managerBranches()->pluck('branches.id');
                $query->whereIn('id', $userBranchIds);
            })
            ->select('id','name')
            ->orderBy('name')
            ->get();
            
        // gợi ý chi nhánh theo query
        $suggestBranch = $request->query('branch');
        $suggestBranchId = ($suggestBranch && $suggestBranch !== 'all') ? (int)$suggestBranch : null;

        return Inertia::render('Manager/Rooms/Create', [
            'branches'        => $branches,
            'suggestBranchId' => $suggestBranchId,
        ]);
    }

    public function store(RoomRequest $request)
    {
        $user = auth()->user();
        $data = $request->validated();
        $data['active'] = (bool)($data['active'] ?? true);

        // Check if manager has access to the selected branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if (!$userBranchIds->contains($data['branch_id'])) {
                return back()->withErrors([
                    'branch_id' => 'Bạn không có quyền tạo phòng cho chi nhánh này.'
                ]);
            }
        }

        Room::create($data);

        return redirect()
            ->route('manager.rooms.index', request()->only('branch')) // Change route name
            ->with('success', 'Tạo phòng thành công.');
    }

    public function edit(Room $room)
    {
        $user = auth()->user();
        
        // Check if manager has access to this room's branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if (!$userBranchIds->contains($room->branch_id)) {
                abort(403, 'Bạn không có quyền chỉnh sửa phòng này.');
            }
        }
        
        $branches = Branch::query()
            ->when($user->hasRole('manager'), function ($query) use ($user) {
                $userBranchIds = $user->managerBranches()->pluck('branches.id');
                $query->whereIn('id', $userBranchIds);
            })
            ->select('id','name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Manager/Rooms/Edit', [
            'room'     => $room->only('id','branch_id','code','name','capacity','active'),
            'branches' => $branches,
        ]);
    }

    public function update(RoomRequest $request, Room $room)
    {
        $user = auth()->user();
        $data = $request->validated();
        $data['active'] = (bool)($data['active'] ?? false);

        // Check if manager has access to both current and new branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if (!$userBranchIds->contains($room->branch_id) || !$userBranchIds->contains($data['branch_id'])) {
                return back()->withErrors([
                    'branch_id' => 'Bạn không có quyền cập nhật phòng này hoặc chuyển sang chi nhánh đã chọn.'
                ]);
            }
        }

        $room->update($data);

        return redirect()
            ->route('manager.rooms.index', request()->only('branch'))
            ->with('success', 'Cập nhật phòng thành công.');
    }

    public function destroy(Room $room)
    {
        $user = auth()->user();
        
        // Check if manager has access to this room's branch
        if ($user->hasRole('manager')) {
            $userBranchIds = $user->managerBranches()->pluck('branches.id');
            if (!$userBranchIds->contains($room->branch_id)) {
                return back()->with('error', 'Bạn không có quyền xóa phòng này.');
            }
        }
        
        try {
            if ($room->classrooms()->exists()) {
                return back()->with('error', 'Không thể xoá phòng vì đang có lớp học sử dụng.');
            }

            $room->delete();
            return back()->with('success', 'Đã xoá phòng.');
        } catch (\Exception $e) {
            return back()->with('error', 'Không thể xoá phòng vì đang được sử dụng.');
        }
    }
}
