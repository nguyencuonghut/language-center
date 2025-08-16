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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classrooms')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('enrolled_at')->nullable();
            $table->unsignedSmallInteger('start_session_no')->default(1); // vào muộn
            $table->enum('status', ['active','transferred','completed','dropped'])->default('active')->index();
            $table->timestamps();

            $table->unique(['student_id','class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
