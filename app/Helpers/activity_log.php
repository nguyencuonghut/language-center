<?php

use App\Services\ActivityLogService;

if (! function_exists('activity_log')) {
    function activity_log(): ActivityLogService {
        return app(ActivityLogService::class);
    }
}
