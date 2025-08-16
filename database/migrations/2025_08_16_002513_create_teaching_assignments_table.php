<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classrooms')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('rate_per_session'); // VND
            $table->date('effective_from')->nullable(); // nếu muốn thay đổi theo mốc thời gian
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['class_id','teacher_id','effective_from'], 'assign_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
    }
};
