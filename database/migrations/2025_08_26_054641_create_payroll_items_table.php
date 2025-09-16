<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('teacher_timesheet_id')->constrained('teacher_timesheets')->cascadeOnUpdate()->cascadeOnDelete();

            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnUpdate()->cascadeOnDelete();

            $table->unsignedBigInteger('amount');   // số tiền 1 buổi
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id','teacher_timesheet_id']); // 1 timesheet chỉ nằm trong 1 payroll
        });
    }

    public function down(): void {
        Schema::dropIfExists('payroll_items');
    }
};
