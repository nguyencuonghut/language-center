<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with(['actor'])
            ->latest()
            ->paginate(30);

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
        ]);
    }
}
