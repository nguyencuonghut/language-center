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

        $logs = $query->orderBy(
            $request->input('sort', 'created_at'),
            $request->input('order', 'desc')
        )->paginate($request->input('per_page', 30));

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
        ]);
    }
}
