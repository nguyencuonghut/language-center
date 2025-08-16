<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRequest;
use App\Models\Room;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $branchParam = $request->query('branch'); // 'all' | null | <id>
        $rooms = Room::query()
            ->when($branchParam && $branchParam !== 'all', fn($q) => $q->where('branch_id', (int)$branchParam))
            ->with('branch:id,name')
            ->orderBy('branch_id')
            ->orderBy('code')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
        ]);
    }

    public function create(Request $request)
    {
        $branches = Branch::select('id','name')->orderBy('name')->get();
        // gợi ý chi nhánh theo query
        $suggestBranch = $request->query('branch');
        $suggestBranchId = ($suggestBranch && $suggestBranch !== 'all') ? (int)$suggestBranch : null;

        return Inertia::render('Rooms/Create', [
            'branches'        => $branches,
            'suggestBranchId' => $suggestBranchId,
        ]);
    }

    public function store(RoomRequest $request)
    {
        $data = $request->validated();
        $data['active'] = (bool)($data['active'] ?? true);

        Room::create($data);

        return redirect()
            ->route('rooms.index', request()->only('branch')) // giữ nguyên query branch nếu có
            ->with('success', 'Tạo phòng thành công.');
    }

    public function edit(Room $room)
    {
        $branches = Branch::select('id','name')->orderBy('name')->get();

        return Inertia::render('Rooms/Edit', [
            'room'     => $room->only('id','branch_id','code','name','capacity','active'),
            'branches' => $branches,
        ]);
    }

    public function update(RoomRequest $request, Room $room)
    {
        $data = $request->validated();
        $data['active'] = (bool)($data['active'] ?? false);

        $room->update($data);

        return redirect()
            ->route('rooms.index', request()->only('branch'))
            ->with('success', 'Cập nhật phòng thành công.');
    }

    public function destroy(Room $room)
    {
        $room->delete();

        return redirect()
            ->route('rooms.index', request()->only('branch'))
            ->with('success', 'Đã xoá phòng.');
    }
}
