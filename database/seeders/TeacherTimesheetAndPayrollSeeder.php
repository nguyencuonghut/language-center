<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\ClassSession;
use App\Models\TeacherTimesheet;
use App\Models\Payroll;
use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TeacherTimesheetAndPayrollSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔍 Seeding teacher timesheets & payrolls...\n";

        // Lấy danh sách giáo viên
        $teachers = Teacher::get();
        if ($teachers->isEmpty()) {
            echo "❌ No teachers found. Please run TeacherDashboardSeeder first.\n";
            return;
        }

        // Lấy các session đã qua
        $sessions = ClassSession::where('date', '<=', Carbon::today())
            ->orderBy('date', 'desc')
            ->take(50)
            ->get();

        if ($sessions->isEmpty()) {
            echo "❌ No class sessions found. Please run TeacherDashboardSeeder first.\n";
            return;
        }

        // Tạo timesheet cho mỗi giáo viên trên các session ngẫu nhiên
        $statusOptions = ['draft', 'approved', 'locked'];
        $timesheetCount = 0;

        foreach ($teachers as $teacher) {
            $teacherSessions = $sessions->random(min(10, $sessions->count()));

            foreach ($teacherSessions as $session) {
                // Tránh tạo trùng timesheet
                if (!TeacherTimesheet::where('teacher_id', $teacher->id)->where('class_session_id', $session->id)->exists()) {
                    TeacherTimesheet::create([
                        'teacher_id' => $teacher->id,
                        'class_session_id' => $session->id,
                        'amount' => rand(180000, 250000), // Giả lập tiền công 1 buổi
                        'status' => Arr::random($statusOptions),
                        'created_at' => Carbon::now()->subMonth()->addDays(rand(0, 27)), // Đảm bảo nằm trong tháng trước
                        'updated_at' => Carbon::now()->subMonth()->addDays(rand(0, 27)),
                    ]);
                    $timesheetCount++;
                }
            }
        }

        echo "✅ Created {$timesheetCount} teacher timesheet records\n";

        // ======= Tạo payrolls theo schema mới =======
        $periodFrom = Carbon::now()->subMonth()->startOfMonth();
        $periodTo = Carbon::now()->subMonth()->endOfMonth();

        // Lấy danh sách branch có timesheet trong kỳ
        $branchIds = Branch::pluck('id')->all();
        $payrollCount = 0;

        foreach ($branchIds as $branchId) {
            // Tổng hợp tiền công của giáo viên dạy tại branch này trong kỳ
            $totalAmount = TeacherTimesheet::whereBetween('created_at', [$periodFrom, $periodTo])
                ->whereHas('classSession.classroom', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                })
                ->sum('amount');

            if ($totalAmount > 0) {
                $code = sprintf('PR-%s-%03d', $periodFrom->format('Y-m'), $branchId);

                Payroll::updateOrCreate(
                    ['code' => $code],
                    [
                        'branch_id' => $branchId,
                        'period_from' => $periodFrom->toDateString(),
                        'period_to' => $periodTo->toDateString(),
                        'total_amount' => $totalAmount,
                        'status' => 'draft',
                        'approved_by' => null,
                        'approved_at' => null,
                    ]
                );
                $payrollCount++;
            }
        }

        // Payroll toàn hệ thống (nếu muốn)
        $totalAll = TeacherTimesheet::whereBetween('created_at', [$periodFrom, $periodTo])->sum('amount');
        if ($totalAll > 0) {
            $code = 'PR-' . $periodFrom->format('Y-m') . '-ALL';
            Payroll::updateOrCreate(
                ['code' => $code],
                [
                    'branch_id' => null,
                    'period_from' => $periodFrom->toDateString(),
                    'period_to' => $periodTo->toDateString(),
                    'total_amount' => $totalAll,
                    'status' => 'draft',
                    'approved_by' => null,
                    'approved_at' => null,
                ]
            );
            $payrollCount++;
        }

        echo "✅ Created/updated {$payrollCount} payroll records\n";
    }
}
