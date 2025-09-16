<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Spatie\Permission\Models\Role;

class LanguageCenterFullSeeder extends Seeder
{
    /**
     * Seed all tables for a realistic language center LMS dataset.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $faker = Faker::create('vi_VN');
            $now = Carbon::now();

            // 1. Roles (Spatie Permission)
            foreach (['admin', 'manager', 'teacher', 'student'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }

            // 2. Users (Admin, Managers, Teachers)
            // ------------------------------------
            // Admin
            $admin = DB::table('users')->updateOrInsert(
                ['email' => 'nguyenvancuong@honghafeed.com.vn'],
                [
                    'name' => 'Quản trị viên',
                    'phone' => '0909000000',
                    'password' => Hash::make('Hongha@123'),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $adminId = DB::table('users')->where('email', 'nguyenvancuong@honghafeed.com.vn')->value('id');
            DB::table('model_has_roles')->updateOrInsert([
                'role_id' => Role::where('name', 'admin')->first()->id,
                'model_type' => 'App\Models\User',
                'model_id' => $adminId,
            ]);

            // Managers
            $managerIds = [];
            foreach (range(1, 3) as $i) {
                DB::table('users')->updateOrInsert(
                    ['email' => "manager{$i}@honghafeed.com.vn"],
                    [
                        'name' => $faker->name('male'),
                        'phone' => $faker->numerify('09########'),
                        'password' => Hash::make('manager123'),
                        'active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $id = DB::table('users')->where('email', "manager{$i}@honghafeed.com.vn")->value('id');
                $managerIds[] = $id;
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => Role::where('name', 'manager')->first()->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $id,
                ]);
            }

            // Teachers
            $teacherIds = [];
            foreach (range(1, 12) as $i) {
                DB::table('users')->updateOrInsert(
                    ['email' => "teacher{$i}@honghafeed.com.vn"],
                    [
                        'name' => $faker->name,
                        'phone' => $faker->numerify('09########'),
                        'password' => Hash::make('teacher123'),
                        'active' => $i % 10 !== 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
                $id = DB::table('users')->where('email', "teacher{$i}@honghafeed.com.vn")->value('id');
                $teacherIds[] = $id;
                DB::table('model_has_roles')->updateOrInsert([
                    'role_id' => Role::where('name', 'teacher')->first()->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $id,
                ]);
            }

            // 3. Branches & manager_branch pivot
            // -----------------------------------
            $branchIds = [];
            foreach (['Hà Nội', 'TP.HCM', 'Đà Nẵng'] as $idx => $city) {
                DB::table('branches')->updateOrInsert([
                    'name' => "Trung tâm $city"
                ], [
                    'address' => $faker->address,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $branchId = DB::table('branches')->where('name', "Trung tâm $city")->value('id');
                $branchIds[] = $branchId;
                // manager_branch pivot
                DB::table('manager_branch')->updateOrInsert([
                    'user_id' => $managerIds[$idx],
                    'branch_id' => $branchId,
                ]);
            }

            // 4. Rooms
            // --------
            foreach ($branchIds as $branchId) {
                foreach (range(1, 5) as $i) {
                    DB::table('rooms')->updateOrInsert([
                        'branch_id' => $branchId,
                        'code' => sprintf('R%02d-%02d', $branchId, $i),
                    ], [
                        'name' => 'Phòng ' . chr(64 + $i) . $i,
                        'capacity' => rand(18, 35),
                        'active' => $i % 5 !== 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            $roomIds = DB::table('rooms')->pluck('id')->all();

            // 5. Courses
            // ----------
            $courseData = [
                ['code' => 'ENB01', 'name' => 'Tiếng Anh Cơ Bản', 'audience' => 'Thiếu nhi', 'language' => 'Tiếng Anh'],
                ['code' => 'ENI01', 'name' => 'Tiếng Anh Giao Tiếp', 'audience' => 'Người lớn', 'language' => 'Tiếng Anh'],
                ['code' => 'JPN01', 'name' => 'Tiếng Nhật Sơ Cấp', 'audience' => 'Sinh viên', 'language' => 'Tiếng Nhật'],
                ['code' => 'KOR01', 'name' => 'Tiếng Hàn Sơ Cấp', 'audience' => 'Người đi làm', 'language' => 'Tiếng Hàn'],
                ['code' => 'CHN01', 'name' => 'Tiếng Trung Giao Tiếp', 'audience' => 'Người lớn', 'language' => 'Tiếng Trung'],
            ];
            $courseIds = [];
            foreach ($courseData as $c) {
                DB::table('courses')->updateOrInsert([
                    'code' => $c['code']
                ], [
                    'name' => $c['name'],
                    'audience' => $c['audience'],
                    'language' => $c['language'],
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $courseIds[] = DB::table('courses')->where('code', $c['code'])->value('id');
            }

            // 6. Classrooms, Schedules, Sessions
            // ----------------------------------
            $classroomIds = [];
            foreach ($branchIds as $branchId) {
                foreach (range(1, 4) as $i) {
                    $start = $now->copy()->subMonths(rand(1, 28))->startOfWeek();
                    $sessionsTotal = Arr::random([8, 10, 12, 16]);
                    $status = Arr::random(['open', 'closed', 'canceled']);
                    $courseId = Arr::random($courseIds);
                    $classCode = "B$branchId-CL$i-" . $start->format('ymd');
                    DB::table('classrooms')->updateOrInsert([
                        'code' => $classCode
                    ], [
                        'name' => $faker->sentence(3),
                        'term_code' => 'T' . $start->format('Y'),
                        'course_id' => $courseId,
                        'branch_id' => $branchId,
                        'start_date' => $start->toDateString(),
                        'sessions_total' => $sessionsTotal,
                        'tuition_fee' => Arr::random([1800000, 2200000, 2500000, 3000000]),
                        'status' => $status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $classId = DB::table('classrooms')->where('code', $classCode)->value('id');
                    $classroomIds[] = $classId;

                    // Schedules: Tue, Thu (18:00-19:30), Sun (8:00-10:00)
                    foreach ([[2, '18:00', '19:30'], [4, '18:00', '19:30'], [0, '08:00', '10:00']] as $sched) {
                        DB::table('class_schedules')->updateOrInsert([
                            'class_id' => $classId,
                            'weekday' => $sched[0],
                            'start_time' => $sched[1],
                            'end_time' => $sched[2],
                        ], [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    // Sessions
                    $schedules = DB::table('class_schedules')->where('class_id', $classId)->get();
                    $cursor = Carbon::parse($start)->startOfDay();
                    $created = 0;
                    $byWeekday = $schedules->groupBy('day_of_week');
                    while ($created < $sessionsTotal) {
                        $weekday = (int) $cursor->format('w');
                        if ($byWeekday->has($weekday)) {
                            foreach ($byWeekday[$weekday] as $slot) {
                                if ($created >= $sessionsTotal) break;
                                DB::table('class_sessions')->updateOrInsert([
                                    'class_id' => $classId,
                                    'session_no' => $created + 1,
                                ], [
                                    'date' => $cursor->toDateString(),
                                    'start_time' => $slot->start_time,
                                    'end_time' => $slot->end_time,
                                    'room_id' => Arr::random($roomIds),
                                    'status' => $status === 'canceled' ? 'canceled' : ($created < $sessionsTotal - 2 ? 'closed' : 'planned'),
                                    'note' => null,
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ]);
                                $created++;
                            }
                        }
                        $cursor->addDay();
                    }
                }
            }

            // 7. Teaching Assignments
            // ----------------------
            foreach ($classroomIds as $classId) {
                $numAssignments = rand(1, 3);
                $startDate = DB::table('classrooms')->where('id', $classId)->value('start_date');
                for ($i = 0; $i < $numAssignments; $i++) {
                    $teacherId = Arr::random($teacherIds);
                    $effectiveFrom = Carbon::parse($startDate)->addDays($i * 15)->toDateString();
                    $effectiveTo = $i === ($numAssignments - 1) ? null : Carbon::parse($startDate)->addDays(($i + 1) * 15)->toDateString();
                    DB::table('teaching_assignments')->updateOrInsert([
                        'teacher_id' => $teacherId,
                        'class_id' => $classId,
                        'effective_from' => $effectiveFrom,
                    ], [
                        'effective_to' => $effectiveTo,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // 8. Students & Enrollments
            // -------------------------
            $studentIds = [];
            foreach (range(1, 10) as $i) {
                DB::table('students')->updateOrInsert([
                    'code' => 'STU' . str_pad($i, 4, '0', STR_PAD_LEFT)
                ], [
                    'name' => $faker->name,
                    'email' => "student{$i}@lms.vn",
                    'phone' => $faker->numerify('09########'),
                    'birthday' => $faker->date('Y-m-d', '-10 years'),
                    'gender' => Arr::random(['male', 'female']),
                    'address' => $faker->address,
                    'parent_name' => $faker->name('female'),
                    'parent_phone' => $faker->numerify('09########'),
                    'note' => Arr::random([null, 'Học viên ưu tú', 'Cần hỗ trợ thêm']),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $studentIds[] = DB::table('students')->where('code', 'STU' . str_pad($i, 4, '0', STR_PAD_LEFT))->value('id');
            }
            foreach ($classroomIds as $classId) {
                $enrolledStudents = Arr::random($studentIds, rand(12, 30));
                foreach ((array)$enrolledStudents as $stuId) {
                    $status = Arr::random(['active', 'completed', 'canceled', 'transferred']);
                    DB::table('enrollments')->updateOrInsert([
                        'student_id' => $stuId,
                        'class_id' => $classId,
                    ], [
                        'enrolled_at' => Carbon::parse(DB::table('classrooms')->where('id', $classId)->value('start_date'))->subDays(rand(0, 30)),
                        'status' => $status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // 9. Invoices & Payments
            // ----------------------
            foreach ($studentIds as $stuId) {
                $enrolls = DB::table('enrollments')->where('student_id', $stuId)->get();
                foreach ($enrolls as $enroll) {
                    $class = DB::table('classrooms')->where('id', $enroll->class_id)->first();
                    if (!$class) continue;
                    $total = $class->tuition_fee;
                    $status = Arr::random(['unpaid', 'partial', 'paid', 'canceled']);
                    DB::table('invoices')->updateOrInsert([
                        'student_id' => $stuId,
                        'total_amount' => $total,
                    ], [
                        'paid_amount' => $status === 'paid' ? $total : ($status === 'partial' ? $total / 2 : 0),
                        'status' => $status,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $invoiceId = DB::table('invoices')->where('student_id', $stuId)->where('total_amount', $total)->value('id');
                    if ($status !== 'unpaid') {
                        DB::table('payments')->updateOrInsert([
                            'invoice_id' => $invoiceId,
                            'amount' => $status === 'paid' ? $total : $total / 2,
                        ], [
                            'payment_date' => $now->subDays(rand(0, 60)),
                            'method' => Arr::random(['cash', 'bank', 'card']),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }

            // 10. Attendances
            // ---------------
            $sessionIds = DB::table('class_sessions')->pluck('id')->all();
            foreach ($sessionIds as $sessionId) {
                $classId = DB::table('class_sessions')->where('id', $sessionId)->value('class_id');
                $studentIdsInClass = DB::table('enrollments')->where('class_id', $classId)->pluck('student_id')->all();
                foreach ($studentIdsInClass as $stuId) {
                    DB::table('attendances')->updateOrInsert([
                        'student_id' => $stuId,
                        'class_session_id' => $sessionId,
                    ], [
                        'status' => Arr::random(['present', 'absent', 'late', 'excused']),
                        'note' => $faker->optional()->sentence,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // 11. Transfers
            // -------------
            foreach (Arr::random($studentIds, 10) as $stuId) {
                $enrolls = DB::table('enrollments')->where('student_id', $stuId)->pluck('class_id')->all();
                if (count($enrolls) < 2) continue;
                $from = $enrolls[0];
                $to = $enrolls[1];
                DB::table('transfers')->updateOrInsert([
                    'student_id' => $stuId,
                    'from_class_id' => $from,
                    'to_class_id' => $to,
                ], [
                    'status' => Arr::random(['pending', 'approved', 'rejected']),
                    'reason' => $faker->sentence,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 12. Teacher Timesheets
            // ---------------------
            foreach ($sessionIds as $sessionId) {
                $classId = DB::table('class_sessions')->where('id', $sessionId)->value('class_id');
                $teacherId = DB::table('teaching_assignments')->where('class_id', $classId)->orderByDesc('effective_from')->value('teacher_id');
                if ($teacherId) {
                    DB::table('teacher_timesheets')->updateOrInsert([
                        'teacher_id' => $teacherId,
                        'class_session_id' => $sessionId,
                    ], [
                        'amount' => Arr::random([200000, 250000, 300000]),
                        'status' => Arr::random(['pending', 'approved', 'rejected']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }
}
