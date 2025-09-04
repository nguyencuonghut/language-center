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
        $this->command->info('Creating demo Transfer data...');

        // Get admin user
        $admin = User::role('admin')->first();
        if (!$admin) {
            $this->command->error('No admin user found!');
            return;
        }

        // Clear existing transfers
        Transfer::truncate();

        // Get some enrollments to create transfers from
        $transferableEnrollments = Enrollment::with(['student', 'classroom'])
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $this->command->info("Found {$transferableEnrollments->count()} enrollments for transfer creation");

        $processedStudents = [];
        $createdTransfers = 0;

        foreach ($transferableEnrollments as $fromEnrollment) {
            // Avoid creating multiple transfers for same student
            if (in_array($fromEnrollment->student_id, $processedStudents)) {
                continue;
            }

            // Find available classes to transfer to
            $availableClasses = Classroom::where('id', '!=', $fromEnrollment->class_id)
                ->whereIn('status', ['open', 'active'])
                ->inRandomOrder()
                ->limit(3)
                ->get();

            if ($availableClasses->isEmpty()) {
                $this->command->warn("No available classes for transfer from class {$fromEnrollment->class_id}");
                continue;
            }

            $toClass = $availableClasses->first();
            $transferStatus = Arr::random(['active', 'active', 'active', 'reverted']); // Mostly active

            try {
                // Create transfer record
                $transfer = Transfer::create([
                    'student_id' => $fromEnrollment->student_id,
                    'from_class_id' => $fromEnrollment->class_id,
                    'to_class_id' => $toClass->id,
                    'effective_date' => now()->addDays(rand(1, 7))->toDateString(),
                    'start_session_no' => rand(1, 3),
                    'reason' => Arr::random([
                        'Xin chuyển lịch học phù hợp hơn',
                        'Chuyển gần nhà hơn',
                        'Theo bạn bè',
                        'Thay đổi nhu cầu học tập',
                        'Chuyển chi nhánh gần hơn'
                    ]),
                    'status' => $transferStatus,
                    'transfer_fee' => Arr::random([0, 50000, 100000]),
                    'created_by' => $admin->id,
                    'processed_at' => now(),
                ]);

                // Mark student as processed
                $processedStudents[] = $fromEnrollment->student_id;
                $createdTransfers++;

                $this->command->info("Created transfer #{$transfer->id}: Student {$fromEnrollment->student_id} from Class {$fromEnrollment->class_id} to Class {$toClass->id} (Status: {$transferStatus})");

                // If transfer is active, create new enrollment and update old one
                if ($transferStatus === 'active') {
                    // Create new enrollment in target class
                    $newEnrollment = Enrollment::create([
                        'student_id' => $fromEnrollment->student_id,
                        'class_id' => $toClass->id,
                        'enrolled_at' => now(),
                        'start_session_no' => $transfer->start_session_no,
                        'status' => 'active',
                    ]);

                    // Update old enrollment to transferred
                    $fromEnrollment->update(['status' => 'transferred']);

                    $this->command->info("  → Created new enrollment #{$newEnrollment->id} and marked old enrollment as transferred");
                }

            } catch (\Exception $e) {
                $this->command->error("Error creating transfer for student {$fromEnrollment->student_id}: " . $e->getMessage());
            }
        }

        $this->command->info("Transfer demo seeding completed. Created {$createdTransfers} transfers.");
    }
}
