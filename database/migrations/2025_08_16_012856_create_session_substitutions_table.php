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
        Schema::create('session_substitutions', function (Blueprint $table) {
            $table->id();

            // Buổi học cụ thể
            $table->foreignId('class_session_id')
                ->constrained('class_sessions')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Giáo viên dạy thay (users.id)
            $table->foreignId('substitute_teacher_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Nếu muốn chi trả khác với rate mặc định
            $table->unsignedBigInteger('rate_override')->nullable();

            // Lý do dạy thay / ghi chú phê duyệt
            $table->text('reason')->nullable();

            // Quy trình phê duyệt (tùy chọn)
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            // Một session có thể có tối đa 1 record dạy thay hiệu lực.
            // Nếu muốn hỗ trợ nhiều giai đoạn trong một buổi, bỏ unique này.
            $table->unique('class_session_id');

            // Truy vấn theo GV thay thế
            $table->index('substitute_teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_substitutions');
    }
};
