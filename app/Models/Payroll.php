<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Payroll extends Model
{
    protected $fillable = [
        'code',
        'branch_id',
        'period_from',
        'period_to',
        'total_amount',
        'status',           // draft | approved | locked
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'approved_at' => 'datetime',
    ];

    // ---- Relations ----
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ---- Scopes ----
    public function scopeStatus($q, string $status)
    {
        return $q->where('status', $status);
    }

    public function scopeInPeriod($q, $from, $to)
    {
        return $q->whereDate('period_from', '>=', $from)
                 ->whereDate('period_to', '<=', $to);
    }

    public function scopeForBranch($q, $branchId = null)
    {
        if ($branchId) return $q->where('branch_id', $branchId);
        return $q->whereNull('branch_id');
    }

    // ---- Helpers ----
    public function isDraft(): bool    { return $this->status === 'draft'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isLocked(): bool   { return $this->status === 'locked'; }
}
