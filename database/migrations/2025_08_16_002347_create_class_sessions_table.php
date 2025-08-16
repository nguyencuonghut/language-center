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
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classrooms')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('session_no')->index();
            $table->date('date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->enum('status', ['planned','canceled','moved'])->default('planned')->index();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['class_id','session_no']);
            $table->index(['room_id','date','start_time','end_time'], 'room_time_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
