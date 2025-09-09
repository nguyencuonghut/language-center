<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HolidayService
{
    /**
     * Trả về holiday (bản ghi đầu tiên khớp) nếu date rơi vào kỳ nghỉ của lớp/chi nhánh/toàn hệ thống.
     */
    public function findFor(int $classId, ?int $branchId, string|\DateTimeInterface $date): ?object
    {
        $d = Carbon::parse($date)->startOfDay();

        // 1) Recurring theo năm (so sánh mm-dd)
        $mmdd = $d->format('m-d');

        // 2) Khoảng ngày thông thường (start_date ≤ d ≤ end_date)
        $ymd  = $d->toDateString();

        // Ưu tiên scope class → branch → global
        $sql = "SELECT * FROM holidays WHERE ( (scope = 'class' AND class_id = ? AND ( (recurring_yearly = 1 AND DATE_FORMAT(start_date, '%m-%d') <= ? AND DATE_FORMAT(end_date, '%m-%d') >= ?) OR (? BETWEEN start_date AND end_date) )) ) ";

        $bindings = [$classId, $mmdd, $mmdd, $ymd];

        if ($branchId) {
            $sql .= " OR ( (scope = 'branch' AND branch_id = ? AND ( (recurring_yearly = 1 AND DATE_FORMAT(start_date, '%m-%d') <= ? AND DATE_FORMAT(end_date, '%m-%d') >= ?) OR (? BETWEEN start_date AND end_date) )) ) ";
            $bindings = array_merge($bindings, [$branchId, $mmdd, $mmdd, $ymd]);
        }

        $sql .= " OR ( (scope = 'global' AND ( (recurring_yearly = 1 AND DATE_FORMAT(start_date, '%m-%d') <= ? AND DATE_FORMAT(end_date, '%m-%d') >= ?) OR (? BETWEEN start_date AND end_date) )) ) ORDER BY CASE scope WHEN 'class' THEN 1 WHEN 'branch' THEN 2 ELSE 3 END, id ASC LIMIT 1";

        $bindings = array_merge($bindings, [$mmdd, $mmdd, $ymd]);

        $row = DB::selectOne($sql, $bindings);

        return $row ?: null;
    }

    public function isHoliday(int $classId, ?int $branchId, string|\DateTimeInterface $date): bool
    {
        return (bool) $this->findFor($classId, $branchId, $date);
    }
}
