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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('status', ['present','absent','late','excused'])->default('present')->index();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['class_session_id','student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
