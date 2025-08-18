<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Database\QueryException;

class BranchController extends Controller
{
    /**
     * Danh sách chi nhánh (có phân trang).
     * Giữ nguyên query string (nếu về sau bạn thêm filter/search).
     */
    public function index(Request $request)
    {
        $branches = Branch::query()
            ->with(['managerUsers:id,name'])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    /**
     * Form tạo chi nhánh.
     */
    public function create()
    {
        $managers = User::role('manager')
            ->select('id','name','email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Branches/Create', [
            'managers' => $managers,
        ]);
    }

    /**
     * Lưu chi nhánh mới.
     */
    public function store(BranchRequest $request)
    {
        $data = $request->validated();
        $data['active'] = (bool)($data['active'] ?? true);

        $branch = Branch::create($data);

        $managerIds = collect($request->input('manager_ids', []))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $branch->managerUsers()->sync($managerIds);

        return redirect()
            ->route('admin.branches.index', request()->query()) // giữ query
            ->with('success', 'Tạo chi nhánh thành công.');
    }

    /**
     * Form sửa chi nhánh.
     */
    public function edit(Branch $branch)
    {
        $managers = User::role('manager')
            ->select('id','name','email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Branches/Edit', [
            'branch' => $branch->only('id', 'code', 'name', 'address', 'phone', 'active'),
            'assignedManagerIds' => $branch->managerUsers()->pluck('users.id'),
            'managers' => $managers,
        ]);
    }

    /**
     * Cập nhật chi nhánh.
     */
    public function update(BranchRequest $request, Branch $branch)
    {
        $data = $request->validated();
        $data['active'] = (bool)($data['active'] ?? false);

        $branch->update($data);

        $managerIds = collect($request->input('manager_ids', []))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $branch->managerUsers()->sync($managerIds);

        return redirect()
            ->route('admin.branches.index', request()->query())
            ->with('success', 'Cập nhật chi nhánh thành công.');
    }

    /**
     * Xoá chi nhánh.
     * Nếu đang bị ràng buộc (rooms/classes/...), báo lỗi thân thiện.
     */
    public function destroy(Branch $branch)
    {
        try {
            // Kiểm tra xem branch có đang được sử dụng không
            if ($branch->rooms()->exists()) {
                return back()
                    ->with('error', 'Không thể xoá chi nhánh vì đang có phòng học.');
            }

            if ($branch->classrooms()->exists()) {
                return back()
                    ->with('error', 'Không thể xoá chi nhánh vì đang có lớp học.');
            }

            $branch->delete();
            return back()->with('success', 'Đã xoá chi nhánh.');

        } catch (QueryException $e) {
            \Log::error('Error deleting branch: ' . $e->getMessage());
            return back()
                ->with('error', 'Không thể xoá chi nhánh vì đang được sử dụng.');
        }
    }
}
