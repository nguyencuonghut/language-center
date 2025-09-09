<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'name',
        'scope',
        'branch_id',
        'class_id',
        'recurring_yearly',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'recurring_yearly' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Scope: holidays for a given branch or global
     */
    public function scopeForBranchOrGlobal($query, $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('scope', 'global')
              ->orWhere(function ($q2) use ($branchId) {
                  $q2->where('scope', 'branch')->where('branch_id', $branchId);
              });
        });
    }
}
