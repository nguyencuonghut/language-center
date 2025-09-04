<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Transfer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransferDataMigrationSeeder extends Seeder
{
    /**
     * Migrate existing transfer data from enrollments to transfers table.
     */
    public function run(): void
    {
        $this->command->info('Starting transfer data migration...');

        // Find students with transferred status enrollments
        $transferredEnrollments = Enrollment::where('status', 'transferred')
            ->with(['student', 'classroom'])
            ->get();

        $this->command->info("Found {$transferredEnrollments->count()} transferred enrollments");

        $migratedCount = 0;

        foreach ($transferredEnrollments as $fromEnrollment) {
            // Find the current active enrollment for this student
            $toEnrollment = Enrollment::where('student_id', $fromEnrollment->student_id)
                ->where('status', 'active')
                ->where('id', '>', $fromEnrollment->id) // Assume newer enrollment is the target
                ->first();

            if (!$toEnrollment) {
                $this->command->warn("No active enrollment found for student {$fromEnrollment->student_id} after transfer from class {$fromEnrollment->class_id}");
                continue;
            }

            // Check if transfer already exists
            $existingTransfer = Transfer::where('student_id', $fromEnrollment->student_id)
                ->where('from_class_id', $fromEnrollment->class_id)
                ->where('to_class_id', $toEnrollment->class_id)
                ->first();

            if ($existingTransfer) {
                $this->command->info("Transfer already exists for student {$fromEnrollment->student_id}");
                continue;
            }

            try {
                // Create transfer record
                Transfer::create([
                    'student_id' => $fromEnrollment->student_id,
                    'from_class_id' => $fromEnrollment->class_id,
                    'to_class_id' => $toEnrollment->class_id,
                    'effective_date' => $toEnrollment->enrolled_at ?? now()->toDateString(),
                    'start_session_no' => $toEnrollment->start_session_no ?? 1,
                    'reason' => 'Migrated from existing data',
                    'status' => 'active',
                    'created_by' => 1, // Assume admin user ID is 1
                    'processed_at' => $toEnrollment->created_at ?? now(),
                    'transfer_fee' => 0,
                    'created_at' => $toEnrollment->created_at ?? now(),
                    'updated_at' => $toEnrollment->updated_at ?? now(),
                ]);

                $migratedCount++;
                $this->command->info("Migrated transfer for student {$fromEnrollment->student_id}: Class {$fromEnrollment->class_id} → Class {$toEnrollment->class_id}");

            } catch (\Exception $e) {
                $this->command->error("Failed to migrate transfer for student {$fromEnrollment->student_id}: " . $e->getMessage());
            }
        }

        $this->command->info("Migration completed. Migrated {$migratedCount} transfers.");
    }
}
