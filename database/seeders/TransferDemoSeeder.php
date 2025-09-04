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
            $transfer = Transfer::create([
                'student_id' => $availableEnrollment->student_id,
                'from_class_id' => $availableEnrollment->class_id,
                'to_class_id' => $toClass->id,
                'effective_date' => $createdAt->copy()->addDays(rand(1, 14))->toDateString(),
                'start_session_no' => rand(1, 5),
                'reason' => $reason,
                'status' => $status,
                'transfer_fee' => $transferFee,
                'created_by' => $managers->random()->id,
                'processed_at' => $createdAt->copy()->addHours(rand(1, 48)),
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
}
