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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Ai thực hiện (có thể null với job hệ thống)
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Hành động ngắn gọn: 'class.session.moved', 'invoice.paid', ...
            $table->string('action')->index();

            // Đối tượng chịu tác động (morph)
            $table->morphs('target'); // target_type (string), target_id (bigint unsigned)

            // Tham số/bối cảnh thêm: JSON
            $table->json('meta')->nullable();

            // Technical info
            $table->string('ip', 45)->nullable();       // IPv4/IPv6
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            // Truy vấn nhanh theo thời gian và action
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
