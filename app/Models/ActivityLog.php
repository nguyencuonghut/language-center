<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'actor_id', 'action', 'target_type', 'target_id',
        'meta', 'ip', 'user_agent',
    ];

    protected $casts = [
        'meta' => AsArrayObject::class,
    ];

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
