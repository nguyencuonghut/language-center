<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Transfer;

class TransferDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating enhanced demo Transfer data...');

        // Get users who can create transfers
        $managers = User::role(['admin', 'manager'])->get();
        if ($managers->isEmpty()) {
            $this->command->error('No admin/manager users found!');
            return;
        }

        // Clear existing transfers
        Transfer::truncate();

        // Create transfers across different time periods for better analytics
        $this->createHistoricalTransfers($managers);
        $this->createCurrentMonthTransfers($managers);
        $this->createRecentTransfers($managers);
        $this->createSpecialTestCases($managers);

        $totalTransfers = Transfer::count();
        $this->command->info("Enhanced transfer demo seeding completed. Created {$totalTransfers} total transfers.");
    }

    /**
     * Create historical transfers (2-6 months ago) for trend analysis
     */
    private function createHistoricalTransfers($managers): void
    {
        $this->command->info('Creating historical transfers...');

        for ($monthsAgo = 6; $monthsAgo >= 2; $monthsAgo--) {
            $baseDate = now()->subMonths($monthsAgo);
            $transfersForMonth = rand(3, 8); // Random number of transfers per month

            for ($i = 0; $i < $transfersForMonth; $i++) {
                $this->createSingleTransfer($managers, $baseDate->copy()->addDays(rand(1, 28)));
            }

            $this->command->info("Created {$transfersForMonth} transfers for " . $baseDate->format('M Y'));
        }
    }

    /**
     * Create current month transfers for active analytics
     */
    private function createCurrentMonthTransfers($managers): void
    {
        $this->command->info('Creating current month transfers...');

        $currentMonth = now()->startOfMonth();
        $transfersThisMonth = rand(10, 20);

        for ($i = 0; $i < $transfersThisMonth; $i++) {
            $randomDate = $currentMonth->copy()->addDays(rand(0, now()->day - 1));
            $this->createSingleTransfer($managers, $randomDate);
        }

        $this->command->info("Created {$transfersThisMonth} transfers for current month");
    }

    /**
     * Create recent transfers (last week) for immediate testing
     */
    private function createRecentTransfers($managers): void
    {
        $this->command->info('Creating recent transfers...');

        $recentTransfers = rand(5, 12);

        for ($i = 0; $i < $recentTransfers; $i++) {
            $randomDate = now()->subDays(rand(1, 7));
            $this->createSingleTransfer($managers, $randomDate);
        }

        $this->command->info("Created {$recentTransfers} recent transfers");
    }

    /**
     * Create special test cases for new features
     */
    private function createSpecialTestCases($managers): void
    {
        $this->command->info('Creating special test cases...');

        // Test case 1: Active transfer that can be reverted
        $this->createActiveTransferForRevertTest($managers);

        // Test case 2: Active transfer that CANNOT be reverted (has attendance)
        $this->createActiveTransferWithAttendance($managers);

        // Test case 3: Multiple reverted transfers with detailed audit trail
        $this->createMultipleRevertedTransfers($managers);

        $this->command->info('Created special test cases for new features');
    }

    /**
     * Create a single transfer with realistic data
     */
    private function createSingleTransfer($managers, Carbon $createdAt): void
    {
        // Get available enrollments (avoid creating multiple transfers for same student)
        $existingTransferStudents = Transfer::pluck('student_id')->toArray();

        $availableEnrollment = Enrollment::with(['student', 'classroom'])
            ->whereNotIn('student_id', $existingTransferStudents)
            ->where('status', 'active')
            ->inRandomOrder()
            ->first();

        if (!$availableEnrollment) {
            return; // No available enrollments
        }

        // Find target classes (exclude current class)
        $targetClasses = Classroom::where('id', '!=', $availableEnrollment->class_id)
            ->whereIn('status', ['open', 'active'])
            ->get();

        if ($targetClasses->isEmpty()) {
            return; // No target classes available
        }

        $toClass = $targetClasses->random();

        // Weighted random status (more active transfers)
        $statusWeights = [
            'active' => 70,     // 70% active
            'reverted' => 20,   // 20% reverted
            'retargeted' => 10  // 10% retargeted
        ];

        $status = $this->weightedRandom($statusWeights);

        // Realistic reasons with different frequencies
        $reasonWeights = [
            'Xin chuyển lịch học phù hợp hơn' => 25,
            'Chuyển gần nhà hơn' => 20,
            'Theo bạn bè' => 15,
            'Thay đổi nhu cầu học tập' => 10,
            'Chuyển chi nhánh gần hơn' => 10,
            'Không phù hợp với giáo viên hiện tại' => 8,
            'Thay đổi lịch làm việc' => 7,
            'Yêu cầu từ phụ huynh' => 5
        ];

        $reason = $this->weightedRandom($reasonWeights);

        // Realistic transfer fees
        $feeWeights = [
            0 => 40,        // 40% free transfers
            50000 => 30,    // 30% 50k fee
            100000 => 20,   // 20% 100k fee
            150000 => 10    // 10% 150k fee
        ];

        $transferFee = $this->weightedRandom($feeWeights);

        try {
            // Generate realistic audit trail data
            $creator = $managers->random();
            $lastModifier = $managers->random();
            $isPriority = rand(1, 100) <= 15; // 15% priority transfers

            // Create realistic status history
            $statusHistory = $this->generateStatusHistory($status, $createdAt, $creator);

            // Create realistic change log
            $changeLog = $this->generateEnhancedChangeLog($createdAt, $creator, $reason, $status);

            // Generate notes with revert information if status is reverted
            $notes = $this->generateNotesBasedOnStatus($status, $reason, $createdAt, $creator);

            // Source system variations
            $sourceSystems = ['manual' => 70, 'api' => 20, 'import' => 10];
            $sourceSystem = $this->weightedRandom($sourceSystems);

            $transfer = Transfer::create([
                'student_id' => $availableEnrollment->student_id,
                'from_class_id' => $availableEnrollment->class_id,
                'to_class_id' => $toClass->id,
                'effective_date' => $createdAt->copy()->addDays(rand(1, 14))->toDateString(),
                'start_session_no' => rand(1, 5),
                'reason' => $reason,
                'notes' => $notes,
                'status' => $status,
                'transfer_fee' => $transferFee,
                'created_by' => $creator->id,
                'processed_at' => $createdAt->copy()->addHours(rand(1, 48)),

                // New audit trail columns
                'status_history' => $statusHistory,
                'change_log' => $changeLog,
                'last_modified_at' => $createdAt->copy()->addHours(rand(1, 48)),
                'last_modified_by' => $lastModifier->id,
                'source_system' => $sourceSystem,
                'admin_notes' => $isPriority ? $this->generateAdminNotes() : null,
                'is_priority' => $isPriority,

                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // Handle enrollment updates based on status
            if ($status === 'active') {
                // Check if enrollment already exists to avoid duplicates
                $existingEnrollment = Enrollment::where('student_id', $availableEnrollment->student_id)
                    ->where('class_id', $toClass->id)
                    ->first();

                if (!$existingEnrollment) {
                    // Create new enrollment in target class
                    Enrollment::create([
                        'student_id' => $availableEnrollment->student_id,
                        'class_id' => $toClass->id,
                        'enrolled_at' => $transfer->effective_date,
                        'start_session_no' => $transfer->start_session_no,
                        'status' => 'active',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }

                // Update old enrollment
                $availableEnrollment->update([
                    'status' => 'transferred',
                    'updated_at' => $createdAt,
                ]);
            }

            // Handle retargeted transfers (5% chance of having retarget data)
            if ($status === 'retargeted' && rand(1, 100) <= 50) {
                $anotherTargetClass = $targetClasses->where('id', '!=', $toClass->id)->random();
                if ($anotherTargetClass) {
                    $transfer->update([
                        'retargeted_to_class_id' => $anotherTargetClass->id,
                        'retargeted_at' => $createdAt->copy()->addDays(rand(1, 30)),
                        'retargeted_by' => $managers->random()->id,
                        // Skip retarget_reason if column doesn't exist
                    ]);
                }
            }

        } catch (\Exception $e) {
            $this->command->error("Error creating transfer: " . $e->getMessage());
        }
    }

    /**
     * Weighted random selection
     */
    private function weightedRandom(array $weights): mixed
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);

        foreach ($weights as $item => $weight) {
            $random -= $weight;
            if ($random <= 0) {
                return $item;
            }
        }

        return array_key_first($weights); // fallback
    }

    /**
     * Generate realistic status history for audit trail
     */
    private function generateStatusHistory(string $finalStatus, Carbon $createdAt, $creator): array
    {
        $history = [
            [
                'from_status' => null,
                'to_status' => 'active',
                'changed_at' => $createdAt->toISOString(),
                'changed_by' => $creator->id,
                'reason' => 'Transfer created and activated'
            ]
        ];

        // Add status changes based on final status
        if ($finalStatus === 'reverted') {
            $revertReasons = [
                'Học viên thay đổi ý định',
                'Lớp đích không phù hợp',
                'Yêu cầu từ phụ huynh',
                'Vấn đề về lịch học',
                'Học viên không thích lớp mới',
                'Giáo viên không phù hợp',
                'Chi phí quá cao'
            ];

            $history[] = [
                'from_status' => 'active',
                'to_status' => 'reverted',
                'changed_at' => $createdAt->copy()->addDays(rand(1, 14))->toISOString(),
                'changed_by' => $creator->id,
                'reason' => $revertReasons[array_rand($revertReasons)]
            ];
        } elseif ($finalStatus === 'retargeted') {
            $history[] = [
                'from_status' => 'active',
                'to_status' => 'retargeted',
                'changed_at' => $createdAt->copy()->addDays(rand(1, 7))->toISOString(),
                'changed_by' => $creator->id,
                'reason' => 'Thay đổi lớp đích theo yêu cầu'
            ];
        }

        return $history;
    }

    /**
     * Generate realistic change log for audit trail
     */
    private function generateChangeLog(Carbon $createdAt, $creator, string $reason): array
    {
        $changes = [
            [
                'timestamp' => $createdAt->toISOString(),
                'user_id' => $creator->id,
                'user_name' => $creator->name,
                'action' => 'created',
                'changes' => [
                    'reason' => ['old' => null, 'new' => $reason],
                    'status' => ['old' => null, 'new' => 'pending']
                ],
                'ip_address' => $this->generateRandomIP(),
                'user_agent' => 'Laravel Seeder'
            ]
        ];

        // Add some random field updates
        if (rand(1, 100) <= 30) { // 30% chance of having additional changes
            $changes[] = [
                'timestamp' => $createdAt->copy()->addHours(rand(1, 12))->toISOString(),
                'user_id' => $creator->id,
                'user_name' => $creator->name,
                'action' => 'updated',
                'changes' => [
                    'reason' => ['old' => $reason, 'new' => $reason . ' (updated)']
                ],
                'ip_address' => $this->generateRandomIP(),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ];
        }

        return $changes;
    }

    /**
     * Generate admin notes for priority transfers
     */
    private function generateAdminNotes(): string
    {
        $notes = [
            'Học viên VIP - xử lý ưu tiên',
            'Yêu cầu khẩn cấp từ phụ huynh',
            'Trường hợp đặc biệt - cần theo dõi',
            'Học viên có vấn đề sức khỏe',
            'Yêu cầu từ ban giám đốc',
            'Học viên xuất sắc - hỗ trợ tối đa',
            'Trường hợp phức tạp - cần giám sát'
        ];

        return $notes[array_rand($notes)];
    }

    /**
     * Generate random IP address for audit trail
     */
    private function generateRandomIP(): string
    {
        return rand(192, 203) . '.' . rand(168, 255) . '.' . rand(1, 254) . '.' . rand(1, 254);
    }

    /**
     * Generate notes based on transfer status
     */
    private function generateNotesBasedOnStatus(string $status, string $reason, Carbon $createdAt, $creator): ?string
    {
        $baseNotes = "Chuyển lớp: {$reason}";

        if ($status === 'reverted') {
            $revertReasons = [
                'Học viên không thích lớp mới, yêu cầu quay lại lớp cũ',
                'Lịch học lớp đích không phù hợp với công việc',
                'Phụ huynh yêu cầu hoàn tác do vấn đề tài chính',
                'Học viên cảm thấy lớp quá khó so với trình độ hiện tại',
                'Xung đột lịch học với hoạt động khác',
                'Giáo viên lớp đích không phù hợp với phong cách học',
                'Chi phí phát sinh thêm quá cao'
            ];

            $revertNotes = [
                'Cần theo dõi thêm để tránh tình trạng tương tự',
                'Học viên đã được tư vấn kỹ lưỡng',
                'Sẽ hỗ trợ tìm lớp phù hợp hơn trong tương lai',
                'Đã giải thích rõ về quy trình chuyển lớp',
                'Cần cải thiện quy trình tư vấn ban đầu'
            ];

            $revertTime = $createdAt->copy()->addDays(rand(1, 14));
            $baseNotes .= "\n\n--- HOÀN TÁC vào " . $revertTime->format('d/m/Y H:i') . " ---\n";
            $baseNotes .= "Lý do: " . $revertReasons[array_rand($revertReasons)] . "\n";
            $baseNotes .= "Ghi chú: " . $revertNotes[array_rand($revertNotes)] . "\n";
            $baseNotes .= "Thực hiện bởi: " . $creator->name . "\n";

        } elseif ($status === 'retargeted') {
            $retargetTime = $createdAt->copy()->addDays(rand(1, 7));
            $baseNotes .= "\n\n--- CHUYỂN HƯỚNG vào " . $retargetTime->format('d/m/Y H:i') . " ---\n";
            $baseNotes .= "Lý do: Thay đổi lớp đích theo yêu cầu mới\n";
            $baseNotes .= "Thực hiện bởi: " . $creator->name . "\n";

        } elseif ($status === 'active' && rand(1, 100) <= 30) {
            // 30% active transfers have additional notes
            $additionalNotes = [
                'Học viên rất hài lòng với việc chuyển lớp',
                'Đã hỗ trợ học viên làm quen với lớp mới',
                'Cần theo dõi tiến độ học tập trong 2 tuần đầu',
                'Học viên có thể cần hỗ trợ thêm về bài tập',
                'Đã thông báo với giáo viên về tình hình học viên'
            ];
            $baseNotes .= "\n\nGhi chú: " . $additionalNotes[array_rand($additionalNotes)];
        }

        return $baseNotes;
    }

    /**
     * Generate realistic change log with enhanced details
     */
    private function generateEnhancedChangeLog(Carbon $createdAt, $creator, string $reason, string $status): array
    {
        $changes = [
            [
                'field' => 'status',
                'old_value' => null,
                'new_value' => 'active',
                'changed_by' => $creator->id,
                'changed_at' => $createdAt->toISOString(),
                'context' => 'Transfer creation'
            ],
            [
                'field' => 'reason',
                'old_value' => null,
                'new_value' => $reason,
                'changed_by' => $creator->id,
                'changed_at' => $createdAt->toISOString(),
                'context' => 'Initial reason set'
            ]
        ];

        // Add status-specific changes
        if ($status === 'reverted') {
            $changes[] = [
                'field' => 'status',
                'old_value' => 'active',
                'new_value' => 'reverted',
                'changed_by' => $creator->id,
                'changed_at' => $createdAt->copy()->addDays(rand(1, 14))->toISOString(),
                'context' => 'Manual revert by user request'
            ];
            $changes[] = [
                'field' => 'enrollment_target',
                'old_value' => 'active',
                'new_value' => 'deleted',
                'changed_by' => $creator->id,
                'changed_at' => $createdAt->copy()->addDays(rand(1, 14))->toISOString(),
                'context' => 'Revert: Xoá enrollment lớp đích'
            ];
            $changes[] = [
                'field' => 'enrollment_source',
                'old_value' => 'transferred',
                'new_value' => 'active',
                'changed_by' => $creator->id,
                'changed_at' => $createdAt->copy()->addDays(rand(1, 14))->toISOString(),
                'context' => 'Revert: Khôi phục enrollment lớp nguồn'
            ];
        }

        return $changes;
    }

    /**
     * Create an active transfer that can be reverted (no attendance/payments)
     */
    private function createActiveTransferForRevertTest($managers): void
    {
        $availableEnrollment = Enrollment::with(['student', 'classroom'])
            ->where('status', 'active')
            ->inRandomOrder()
            ->first();

        if (!$availableEnrollment) return;

        $targetClass = Classroom::where('id', '!=', $availableEnrollment->class_id)
            ->whereIn('status', ['open', 'active'])
            ->inRandomOrder()
            ->first();

        if (!$targetClass) return;

        $creator = $managers->random();
        $createdAt = now()->subDays(rand(1, 3));

        Transfer::create([
            'student_id' => $availableEnrollment->student_id,
            'from_class_id' => $availableEnrollment->class_id,
            'to_class_id' => $targetClass->id,
            'effective_date' => $createdAt->copy()->addDays(1)->toDateString(),
            'start_session_no' => 1,
            'reason' => 'TEST CASE: Transfer có thể hoàn tác',
            'notes' => 'Đây là test case để kiểm tra tính năng revert với reason và notes.',
            'status' => 'active',
            'transfer_fee' => 0,
            'created_by' => $creator->id,
            'processed_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * Create an active transfer that CANNOT be reverted (has attendance)
     */
    private function createActiveTransferWithAttendance($managers): void
    {
        $availableEnrollment = Enrollment::with(['student', 'classroom'])
            ->where('status', 'active')
            ->inRandomOrder()
            ->first();

        if (!$availableEnrollment) return;

        $targetClass = Classroom::where('id', '!=', $availableEnrollment->class_id)
            ->whereIn('status', ['open', 'active'])
            ->inRandomOrder()
            ->first();

        if (!$targetClass) return;

        $creator = $managers->random();
        $createdAt = now()->subDays(rand(5, 10));

        Transfer::create([
            'student_id' => $availableEnrollment->student_id,
            'from_class_id' => $availableEnrollment->class_id,
            'to_class_id' => $targetClass->id,
            'effective_date' => $createdAt->copy()->addDays(1)->toDateString(),
            'start_session_no' => 1,
            'reason' => 'TEST CASE: Transfer không thể hoàn tác',
            'notes' => 'Đây là test case mô phỏng transfer đã có attendance/payments.',
            'status' => 'active',
            'transfer_fee' => 100000,
            'created_by' => $creator->id,
            'processed_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * Create multiple reverted transfers with detailed audit trail
     */
    private function createMultipleRevertedTransfers($managers): void
    {
        for ($i = 0; $i < 3; $i++) {
            $availableEnrollment = Enrollment::with(['student', 'classroom'])
                ->where('status', 'active')
                ->inRandomOrder()
                ->first();

            if (!$availableEnrollment) continue;

            $targetClass = Classroom::where('id', '!=', $availableEnrollment->class_id)
                ->whereIn('status', ['open', 'active'])
                ->inRandomOrder()
                ->first();

            if (!$targetClass) continue;

            $creator = $managers->random();
            $createdAt = now()->subDays(rand(10, 30));
            $revertedAt = $createdAt->copy()->addDays(rand(1, 7));

            $revertReasons = [
                'Học viên không hài lòng với lớp mới',
                'Lịch học không phù hợp với công việc',
                'Yêu cầu từ phụ huynh'
            ];

            $revertReason = $revertReasons[array_rand($revertReasons)];

            $notes = "Chuyển lớp ban đầu: Yêu cầu thay đổi lịch học\n\n";
            $notes .= "--- HOÀN TÁC vào " . $revertedAt->format('d/m/Y H:i') . " ---\n";
            $notes .= "Lý do: {$revertReason}\n";
            $notes .= "Ghi chú: Đã tư vấn kỹ lưỡng cho học viên\n";
            $notes .= "Thực hiện bởi: " . $creator->name . "\n";

            Transfer::create([
                'student_id' => $availableEnrollment->student_id,
                'from_class_id' => $availableEnrollment->class_id,
                'to_class_id' => $targetClass->id,
                'effective_date' => $createdAt->copy()->addDays(1)->toDateString(),
                'start_session_no' => 1,
                'reason' => 'TEST: Yêu cầu thay đổi lịch học',
                'notes' => $notes,
                'status' => 'reverted',
                'transfer_fee' => 0,
                'created_by' => $creator->id,
                'processed_at' => $createdAt,
                'reverted_at' => $revertedAt,
                'reverted_by' => $creator->id,
                'last_modified_at' => $revertedAt,
                'last_modified_by' => $creator->id,
                'created_at' => $createdAt,
                'updated_at' => $revertedAt,
            ]);
        }
    }
}
