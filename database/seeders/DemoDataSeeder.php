<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Carbon\Carbon;

// Giữ đúng tên models bạn đang dùng trong dự án
use App\Models\User;
use App\Models\Branch;
use App\Models\Room;
use App\Models\Course;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\ClassSession;
use App\Models\TeachingAssignment;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ---------------------------
            // 1) USERS + ROLES (Spatie)
            // ---------------------------
            $admin = User::firstOrCreate(
                ['email' => 'nguyenvancuong@honghafeed.com.vn'],
                ['name' => 'Tony Nguyen', 'password' => Hash::make('Hongha@123')]
            );
            $admin->assignRole('admin');

            $managers = collect(range(1, 2))->map(function ($i) {
                $u = User::firstOrCreate(
                    ['email' => "manager{$i}@example.com"],
                    ['name' => "Manager {$i}", 'password' => Hash::make('password')]
                );
                $u->assignRole('manager');
                return $u;
            });

            $teachers = collect(range(1, 5))->map(function ($i) {
                $u = User::firstOrCreate(
                    ['email' => "teacher{$i}@example.com"],
                    ['name' => "Teacher {$i}", 'password' => Hash::make('password')]
                );
                $u->assignRole('teacher');
                return $u;
            });

            // ---------------------------
            // 2) BRANCHES & ROOMS
            // ---------------------------
            $branches = Branch::factory()->count(2)->create();

            // Mỗi branch 5 phòng — đảm bảo code phòng unique theo nhánh (không phụ thuộc unique() của faker)
            foreach ($branches as $b) {
                for ($i = 1; $i <= 5; $i++) {
                    Room::create([
                        'branch_id' => $b->id,
                        'code'      => sprintf('R%02d-%02d', $b->id, $i), // ví dụ: R01-01, R01-02,...
                        'name'      => 'Phòng ' . chr(64 + $i) . $i,       // Phòng A1, B2...
                        'capacity'  => rand(18, 35),
                        'active'    => true,
                    ]);
                }
            }

            // Gán manager ↔ chi nhánh (ví dụ: manager1 quản cả 2, manager2 quản chi nhánh đầu)
            if ($managers->count()) {
                $managers[0]->managerBranches()->sync($branches->pluck('id'));          // nhiều chi nhánh
                if ($managers->count() > 1) {
                    $managers[1]->managerBranches()->sync([$branches->first()->id]);    // 1 chi nhánh
                }
            }

            // ---------------------------
            // 3) COURSES
            // ---------------------------
            $courses = Course::factory()->count(5)->create();

            // ---------------------------
            // 4) CLASSES + schedules + sessions + assignment
            // ---------------------------
            $classes = collect();
            foreach ($branches as $branch) {
                $classes = $classes->merge(
                    Classroom::factory()->count(4)->create([
                        'branch_id'  => $branch->id,
                        'course_id'  => $courses->random()->id,
                        'teacher_id' => optional($teachers->random())->id, // có thể null (xử lý phía dưới)
                    ])
                );
            }

            foreach ($classes as $class) {
                // 2 lịch/tuần: Thứ 5 (19-21h) & Chủ nhật (8-10h)
                $scheds = [
                    ['weekday' => 4, 'start' => '19:00', 'end' => '21:00'], // Thu 5
                    ['weekday' => 0, 'start' => '08:00', 'end' => '10:00'], // CN
                ];

                foreach ($scheds as $s) {
                    ClassSchedule::create([
                        'class_id'   => $class->id,
                        'weekday'    => $s['weekday'],
                        'start_time' => $s['start'],
                        'end_time'   => $s['end'],
                    ]);
                }

                // assignment + rate mặc định (nếu có teacher gốc)
                if ($class->teacher_id) {
                    TeachingAssignment::create([
                        'class_id'         => $class->id,
                        'teacher_id'       => $class->teacher_id,
                        'rate_per_session' => Arr::random([200000, 250000, 300000]),
                    ]);
                }

                // Generate sessions theo lịch tuần cho đến đủ sessions_total
                $this->generateSessions($class->id, $class->start_date, (int) $class->sessions_total);
            }

            // -----------------------------------------------------
            // 4.1) SEEDER: SESSION SUBSTITUTIONS (dạy thay)  (NEW)
            // -----------------------------------------------------
            // Quy ước: mỗi lớp chọn ngẫu nhiên 0–2 buổi để có giáo viên dạy thay;
            // Chọn teacher khác teacher gốc; rate_override ~ 50% trường hợp.
            foreach ($classes as $class) {
                // Lấy danh sách session của lớp
                $sessionIds = ClassSession::where('class_id', $class->id)
                    ->inRandomOrder()
                    ->limit(rand(0, 2))   // 0–2 buổi có dạy thay
                    ->pluck('id');

                if ($sessionIds->isEmpty()) continue;

                // Chọn teacher thay thế khác teacher gốc (nếu có)
                $originalId = $class->teacher_id;
                $subTeacher = User::role('teacher')
                    ->when($originalId, fn($q) => $q->where('id', '!=', $originalId))
                    ->inRandomOrder()
                    ->first();

                if (!$subTeacher) continue;

                $approverId = ($managers->count() ? $managers->random()->id : $admin->id);

                foreach ($sessionIds as $sid) {
                    // Đảm bảo không trùng (migration có unique(class_session_id))
                    $exists = DB::table('session_substitutions')->where('class_session_id', $sid)->exists();
                    if ($exists) continue;

                    DB::table('session_substitutions')->insert([
                        'class_session_id'      => $sid,
                        'substitute_teacher_id' => $subTeacher->id,
                        'rate_override'         => rand(0,1) ? Arr::random([200000, 250000, 300000]) : null,
                        'reason'                => Arr::random(['GV bận việc cá nhân','Ốm đột xuất','Đổi lịch đột xuất']),
                        'approved_by'           => $approverId,
                        'approved_at'           => now(),
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }
            }

            // ------------------------------------
            // 4.2) SEEDER: HOLIDAYS (ngày nghỉ)  (NEW)
            // ------------------------------------
            // Tạo một số ngày nghỉ mẫu: global (lặp hàng năm), theo branch, theo class
            $now = now();

            $holidayRows = [
                // Global, lặp lại hàng năm: Tết Dương lịch 01/01
                [
                    'start_date'       => Carbon::create($now->year, 1, 1)->toDateString(),
                    'end_date'         => Carbon::create($now->year, 1, 1)->toDateString(),
                    'name'             => 'Tết Dương lịch',
                    'scope'            => 'global',
                    'branch_id'        => null,
                    'class_id'         => null,
                    'recurring_yearly' => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            ];

            // Branch holiday: bảo trì cơ sở vật chất (3 ngày), áp dụng chi nhánh đầu tiên (nếu có)
            $firstBranch = $branches->first();
            if ($firstBranch) {
                $start = $now->copy()->addDays(10)->toDateString();
                $end   = $now->copy()->addDays(12)->toDateString();
                $holidayRows[] = [
                    'start_date'       => $start,
                    'end_date'         => $end,
                    'name'             => 'Bảo trì cơ sở vật chất',
                    'scope'            => 'branch',
                    'branch_id'        => $firstBranch->id,
                    'class_id'         => null,
                    'recurring_yearly' => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            // Class holiday: nghỉ giữa kỳ 1 ngày cho một lớp bất kỳ (nếu có)
            $firstClass = $classes->first();
            if ($firstClass) {
                $mid = Carbon::parse($firstClass->start_date)->addDays(14)->toDateString();
                $holidayRows[] = [
                    'start_date'       => $mid,
                    'end_date'         => $mid,
                    'name'             => 'Nghỉ giữa kỳ',
                    'scope'            => 'class',
                    'branch_id'        => null,
                    'class_id'         => $firstClass->id,
                    'recurring_yearly' => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }

            DB::table('holidays')->insert($holidayRows);

            // ---------------------------
            // 5) STUDENTS + ENROLLMENTS + INVOICES/PAYMENTS
            // ---------------------------
            $students = Student::factory()->count(60)->create();

            foreach ($classes as $class) {
                $n = rand(10, 20);
                $selected = $students->random($n);

                foreach ($selected as $stu) {
                    Enrollment::firstOrCreate(
                        [
                            'student_id' => $stu->id,
                            'class_id'   => $class->id,
                        ],
                        [
                            'enrolled_at'      => Carbon::parse($class->start_date)->subDays(rand(0, 7)),
                            'start_session_no' => Arr::random([1, 1, 1, 3, 5]), // đa số vào từ đầu, một số vào muộn
                            'status'           => 'active',
                        ]
                    );

                    // Hóa đơn học phí cơ bản
                    $invoice = Invoice::create([
                        'branch_id' => $class->branch_id,
                        'student_id'=> $stu->id,
                        'class_id'  => $class->id,
                        'total'     => (int) $class->tuition_fee,
                        'status'    => 'unpaid',
                        'due_date'  => Carbon::parse($class->start_date)->addDays(10),
                    ]);

                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'type'        => 'tuition',
                        'description' => 'Học phí khóa ' . $class->code,
                        'qty'         => 1,
                        'unit_price'  => (int) $class->tuition_fee,
                        'amount'      => (int) $class->tuition_fee,
                    ]);

                    // Một số thanh toán 1 phần hoặc đủ
                    if (rand(0, 1)) {
                        $amount = Arr::random([500000, 1000000, (int) $class->tuition_fee]);
                        Payment::create([
                            'invoice_id' => $invoice->id,
                            'method'     => Arr::random(['cash', 'bank', 'momo']),
                            'paid_at'    => now()->subDays(rand(0, 5)),
                            'amount'     => (int) $amount,
                            'ref_no'     => 'PMT' . rand(1000, 9999),
                        ]);

                        if ($amount >= $class->tuition_fee) {
                            $invoice->update(['status' => 'paid']);
                        } elseif ($amount > 0) {
                            $invoice->update(['status' => 'partial']);
                        }
                    }
                }
            }
            // ---------------------------
            // 6) ATTENDANCES (điểm danh demo)
            // ---------------------------
            foreach ($classes as $class) {
                $sessions  = ClassSession::where('class_id', $class->id)->get();
                $enrolls   = Enrollment::where('class_id', $class->id)->pluck('student_id');

                foreach ($sessions as $ses) {
                    foreach ($enrolls as $stuId) {
                        // Tỉ lệ ngẫu nhiên: 80% present, 10% absent, 5% late, 5% excused
                        $status = Arr::random([
                            'present','present','present','present','present',
                            'present','present','present',
                            'absent','late','excused'
                        ]);

                        DB::table('attendances')->updateOrInsert(
                            ['class_session_id' => $ses->id, 'student_id' => $stuId],
                            [
                                'status'     => $status,
                                'note'       => $status !== 'present' ? Arr::random(['Ốm','Xin nghỉ','Kẹt xe',null]) : null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }
            }
        });
    }

    /**
     * Sinh class_sessions theo lịch tuần cho đến khi đủ tổng số buổi.
     */
    private function generateSessions(int $classId, string $startDate, int $total): void
    {
        $schedules = ClassSchedule::where('class_id', $classId)->get();
        if ($schedules->isEmpty() || $total <= 0) {
            return;
        }

        $cursor    = Carbon::parse($startDate)->startOfDay();
        $created   = 0;
        $byWeekday = $schedules->groupBy('weekday');

        while ($created < $total) {
            $weekday = (int) $cursor->format('w'); // 0..6 (CN..T7)
            if ($byWeekday->has($weekday)) {
                foreach ($byWeekday[$weekday] as $slot) {
                    if ($created >= $total) {
                        break;
                    }
                    ClassSession::create([
                        'class_id'   => $classId,
                        'session_no' => $created + 1,
                        'date'       => $cursor->toDateString(),
                        'start_time' => $slot->start_time,
                        'end_time'   => $slot->end_time,
                        'room_id'    => null,
                        'status'     => 'planned',
                        'note'       => null,
                    ]);
                    $created++;
                }
            }
            $cursor->addDay();
        }
    }
}
