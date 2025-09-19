<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'gender', 'dob', 'email', 'phone', 'address', 'active'
    ];

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function ledgerBalance(): float
    {
        $sums = DB::table('student_ledger_entries')
            ->selectRaw('COALESCE(SUM(debit),0) as t_debit, COALESCE(SUM(credit),0) as t_credit')
            ->where('student_id', $this->id)
            ->first();

        return (float) $sums->t_debit - (float) $sums->t_credit;
    }

    public function recentLedgerEntries(int $limit = 10)
    {
        return StudentLedgerEntry::where('student_id', $this->id)
            ->orderByDesc('entry_date')->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
