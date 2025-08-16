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
        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classrooms')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0=Sun .. 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['class_id','weekday','start_time','end_time'], 'cls_sched_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};
