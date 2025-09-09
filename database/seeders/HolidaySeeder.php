<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $holidays = [
            // Các ngày lễ lớn Việt Nam (global, recurring_yearly)
            [
                'start_date' => '2025-01-01',
                'end_date' => '2025-01-01',
                'name' => 'Tết Dương lịch',
                'scope' => 'global',
                'branch_id' => null,
                'class_id' => null,
                'recurring_yearly' => true,
            ],
            [
                'start_date' => '2025-04-30',
                'end_date' => '2025-04-30',
                'name' => 'Giải phóng miền Nam',
                'scope' => 'global',
                'branch_id' => null,
                'class_id' => null,
                'recurring_yearly' => true,
            ],
            [
                'start_date' => '2025-05-01',
                'end_date' => '2025-05-01',
                'name' => 'Quốc tế Lao động',
                'scope' => 'global',
                'branch_id' => null,
                'class_id' => null,
                'recurring_yearly' => true,
            ],
            [
                'start_date' => '2025-09-02',
                'end_date' => '2025-09-02',
                'name' => 'Quốc khánh',
                'scope' => 'global',
                'branch_id' => null,
                'class_id' => null,
                'recurring_yearly' => true,
            ],
            // Tết Nguyên Đán 2025 (có thể điều chỉnh theo từng năm)
            [
                'start_date' => '2025-01-28',
                'end_date' => '2025-02-01',
                'name' => 'Tết Nguyên Đán',
                'scope' => 'global',
                'branch_id' => null,
                'class_id' => null,
                'recurring_yearly' => false,
            ],
        ];
        foreach ($holidays as $h) {
            \DB::table('holidays')->updateOrInsert([
                'start_date' => $h['start_date'],
                'end_date' => $h['end_date'],
                'scope' => $h['scope'],
                'branch_id' => $h['branch_id'],
                'class_id' => $h['class_id'],
            ], $h);
        }
    }
}
