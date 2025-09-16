<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->with(['actor']);

        // Tìm kiếm theo action, target_id, ip, meta, actor_name, user_agent
        if ($q = $request->input('q')) {
            $query->where(function($sub) use ($q) {
                $sub->where('action', 'like', "%$q%")
                    ->orWhere('target_id', 'like', "%$q%")
                    ->orWhere('ip', 'like', "%$q%")
                    ->orWhere('meta', 'like', "%$q%")
                    ->orWhere('user_agent', 'like', "%$q%")
                    ->orWhereHas('actor', function($actor) use ($q) {
                        $actor->where('name', 'like', "%$q%");
                    });
            });
        }
        // Lọc theo người thực hiện (actor_id)
        if ($actorId = $request->input('actor_id')) {
            $query->where('actor_id', $actorId);
        }
        // Lấy danh sách actors cho filter dropdown
        $actors = \App\Models\User::whereIn('id', ActivityLog::distinct()->pluck('actor_id'))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Lọc theo action
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Lấy danh sách actions cho filter dropdown
        $actions = ActivityLog::distinct()->orderBy('action')->pluck('action')->filter()->values();

        // Lọc theo target_type
        if ($targetType = $request->input('target_type')) {
            $query->where('target_type', $targetType);
        }

        // Lấy danh sách target_types cho filter dropdown
        $target_types = ActivityLog::distinct()->orderBy('target_type')->pluck('target_type')->filter()->values();

        $logs = $query->orderBy(
            $request->input('sort', 'created_at'),
            $request->input('order', 'desc')
        )->paginate($request->input('per_page', 30));

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
            'actors' => $actors,
            'actions' => $actions,
            'target_types' => $target_types,
            'filters' => $request->all(['q', 'actor_id', 'action', 'target_type', 'date_from', 'date_to', 'perPage', 'sort', 'order']),
        ]);
    }
}
