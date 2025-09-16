<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Filter theo ngày tạo (created_at)
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

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

    // Xuất logs ra file CSV
    public function export(Request $request)
    {
        $query = ActivityLog::query()->with(['actor']);

        // Áp dụng các filter giống index
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
        if ($actorId = $request->input('actor_id')) {
            $query->where('actor_id', $actorId);
        }
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($targetType = $request->input('target_type')) {
            $query->where('target_type', $targetType);
        }
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->orderBy('created_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="activity_logs.csv"',
        ];

        return new StreamedResponse(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // Ghi BOM để Excel nhận UTF-8
            fwrite($handle, "\xEF\xBB\xBF");
            // Header
            fputcsv($handle, [
                'ID', 'Thời gian', 'Người thực hiện', 'Action', 'Target type', 'Target ID', 'IP', 'User Agent', 'Meta'
            ]);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at ? $log->created_at->format('Y-m-d H:i') : '',
                    $log->actor?->name ?? 'System',
                    $log->action,
                    $log->target_type,
                    $log->target_id,
                    $log->ip,
                    $log->user_agent,
                    // Sửa dòng này: luôn ép về chuỗi JSON nếu là array/object
                    is_array($log->meta) || $log->meta instanceof \ArrayObject
                        ? json_encode($log->meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
                        : (string)$log->meta,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
