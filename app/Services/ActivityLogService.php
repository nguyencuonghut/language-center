<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Ghi một log nghiệp vụ.
     *
     * @param null|int $actorId  user id đang thực hiện (nullable)
     * @param string   $action   ví dụ 'invoice.paid', 'transfer.created', 'timesheet.approved'
     * @param Model    $target   model liên quan (morph)
     * @param array    $meta     dữ liệu bổ sung: ['amount'=>..., 'note'=>..., ...]
     * @param Request|null $request  để lấy ip/user_agent (nếu null sẽ cố gắng lấy từ request())
     */
    public function log(?int $actorId, string $action, Model $target, array $meta = [], ?Request $request = null): ActivityLog
    {
        $req = $request ?? request();

        return ActivityLog::create([
            'actor_id'   => $actorId,
            'action'     => $action,
            'target_type'=> $target->getMorphClass(),
            'target_id'  => $target->getKey(),
            'meta'       => $meta ?: null,
            'ip'         => $req?->ip(),
            'user_agent' => $req?->userAgent(),
        ]);
    }
}
