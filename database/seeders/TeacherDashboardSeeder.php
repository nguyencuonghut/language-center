<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\Room;
use Spatie\Permission\Models\Role;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class TeacherDashboardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔍 Checking existing data...\n";

        // Kiểm tra dữ liệu hiện có
        $existingTeachers = Teacher::count();
        $existingClassrooms = Classroom::count();
        $existingStudents = Student::count();

        echo "📊 Found: {$existingTeachers} teachers, {$existingClassrooms} classrooms, {$existingStudents} students\n";

        // Lấy teacher đầu tiên hoặc tạo teacher test
        $teacher = Teacher::first();
        if (!$teacher) {
            echo "🧑‍🏫 Creating teacher...\n";
            $teacher = Teacher::create([
                'user_id' => User::firstOrCreate(
                    ['email' => 'teacher@test.com'],
                    [
                        'name' => 'Test Teacher',
                        'password' => Hash::make('password'),
                    ]
                )->id,
                'code' => 'T0001',
                'name' => 'Test Teacher',
                'phone' => '0123456789',
                'address' => '123 Test St, Test City',
                'education_level' => 'master',
                'notes' => 'This is a test teacher account.',
            ]);
            echo "✅ Teacher created: " . $teacher->user->email . " / password\n";
        } else {
            echo "✅ Using existing teacher: " . $teacher->user->email . "\n";
        }

        // Lấy classrooms hiện có
        $classrooms = Classroom::take(5)->get();

        if ($classrooms->isEmpty()) {
            echo "❌ No classrooms found. Please run other seeders first.\n";
            return;
        }

        echo "📚 Using existing classrooms: " . $classrooms->pluck('name')->join(', ') . "\n";

        // Tạo class sessions nếu chưa có đủ
        echo "📅 Creating additional class sessions...\n";

        foreach ($classrooms as $classIndex => $classroom) {
            $this->createSessionsForClassroom($classroom, $classIndex);
        }

        // Tạo attendance records cho sessions đã qua
        echo "✅ Creating attendance records...\n";
        $this->createAttendanceRecords();

        // Tạo sessions cho hôm nay nếu chưa có
        echo "📅 Ensuring today's sessions exist...\n";
        $this->ensureTodaySessions($classrooms);

        $this->command->info('✅ Teacher Dashboard test data updated successfully!');
        $this->command->info('👤 Teacher: ' . $teacher->email . ' / password');
        $this->command->info('📚 Using ' . $classrooms->count() . ' existing classrooms');
        $this->command->info('📅 Total sessions: ' . ClassSession::count());
        $this->command->info('✔️ Total attendance records: ' . Attendance::count());
    }

    private function createSessionsForClassroom($classroom, $classIndex)
    {
        $startDate = Carbon::now()->subMonths(1)->startOfWeek();
        $endDate = Carbon::now()->addWeeks(2)->endOfWeek();
        $currentDate = $startDate->copy();

        // Schedule patterns cho các lớp khác nhau
        $schedulePatterns = [
            0 => ['days' => [1, 3, 5], 'time' => ['08:00', '10:00']], // Mon, Wed, Fri morning
            1 => ['days' => [2, 4], 'time' => ['14:00', '16:00']],    // Tue, Thu afternoon
            2 => ['days' => [1, 3, 5], 'time' => ['19:00', '21:00']], // Mon, Wed, Fri evening
            3 => ['days' => [2, 4, 6], 'time' => ['09:00', '11:00']], // Tue, Thu, Sat morning
            4 => ['days' => [1, 3], 'time' => ['16:00', '18:00']],    // Mon, Wed afternoon
        ];

        $pattern = $schedulePatterns[$classIndex % count($schedulePatterns)];
        $sessionCount = 0;

        // Lấy session_no lớn nhất hiện có cho class_id này
        $maxSessionNo = ClassSession::where('class_id', $classroom->id)->max('session_no') ?? 0;

        $roomIds = Room::pluck('id')->all();
        while ($currentDate->lte($endDate) && $sessionCount < 30) { // Limit sessions
            if (in_array($currentDate->dayOfWeek, $pattern['days'])) {
                $existingSession = ClassSession::where('class_id', $classroom->id)
                    ->whereDate('date', $currentDate->toDateString())
                    ->first();

                if (!$existingSession) {
                    ClassSession::create([
                        'class_id' => $classroom->id,
                        'session_no' => $maxSessionNo + $sessionCount + 1, // Đảm bảo không trùng
                        'date' => $currentDate->toDateString(),
                        'start_time' => $currentDate->toDateString() . ' ' . $pattern['time'][0] . ':00',
                        'end_time' => $currentDate->toDateString() . ' ' . $pattern['time'][1] . ':00',
                        'room_id' => $roomIds ? Arr::random($roomIds) : null,
                        'status' => Arr::random(['planned', 'canceled', 'moved']),
                        'note' => 'Auto-generated session for ' . $classroom->name,
                    ]);
                    $sessionCount++;
                }
            }
            $currentDate->addDay();
        }

        echo "  → Created {$sessionCount} sessions for {$classroom->name}\n";
    }

    private function createAttendanceRecords()
    {
        $pastSessions = ClassSession::where('date', '<', Carbon::today())
            ->whereDoesntHave('attendances')
            ->with('classroom')
            ->get();

        $attendanceCount = 0;

        foreach ($pastSessions as $session) {
            $enrollments = Enrollment::where('class_id', $session->class_id)
                ->where('status', 'active')
                ->get();

            foreach ($enrollments as $enrollment) {
                // Tạo attendance patterns thực tế
                $studentIndex = $enrollment->student_id % 10;
                $baseRate = $studentIndex <= 7 ? rand(75, 95) : rand(45, 70); // 80% good, 20% poor attendance

                $willAttend = rand(1, 100) <= $baseRate;

                if ($willAttend || rand(1, 100) <= 15) { // Ensure some records exist
                    Attendance::create([
                        'student_id' => $enrollment->student_id,
                        'class_session_id' => $session->id,
                        'status' => $willAttend ? 'present' : (rand(1, 100) <= 15 ? 'late' : 'absent'),
                        'note' => null,
                    ]);
                    $attendanceCount++;
                }
            }
        }

        echo "  → Created {$attendanceCount} attendance records\n";
    }

    private function ensureTodaySessions($classrooms)
    {
        $today = Carbon::today();
        $todaySessionsCount = 0;

        // Đảm bảo có ít nhất 2 sessions hôm nay để test
        foreach ($classrooms->take(2) as $index => $classroom) {
            $existingTodaySession = ClassSession::where('class_id', $classroom->id)
                ->whereDate('date', $today->toDateString())
                ->first();

            if (!$existingTodaySession) {
                $times = [
                    ['08:00', '10:00'],
                    ['14:00', '16:00'],
                ];

                ClassSession::create([
                    'class_id' => $classroom->id,
                    'session_no' => ClassSession::where('class_id', $classroom->id)->count() + 1,
                    'date' => $today->toDateString(),
                    'start_time' => $today->toDateString() . ' ' . $times[$index][0] . ':00',
                    'end_time' => $today->toDateString() . ' ' . $times[$index][1] . ':00',
                    'room_id' => Room::inRandomOrder()->first()?->id,
                    'status' => 'planned',
                    'note' => 'Today\'s test session',
                ]);
                $todaySessionsCount++;
            }
        }

        echo "  → Ensured {$todaySessionsCount} sessions for today\n";
    }
}
