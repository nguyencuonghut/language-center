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
        Schema::create('holidays', function (Blueprint $table) {$table->id();

            // Khoảng ngày nghỉ (cho phép 1 ngày hoặc nhiều ngày liên tiếp)
            $table->date('start_date')->index();
            $table->date('end_date')->index();

            $table->string('name'); // Tên ngày lễ/nghỉ

            // Phạm vi áp dụng:
            // - global: áp dụng toàn hệ thống
            // - branch: theo chi nhánh (branch_id phải có)
            // - class : theo lớp (class_id phải có)
            $table->enum('scope', ['global', 'branch', 'class'])->default('global')->index();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('class_id')
                ->nullable()
                ->constrained('classrooms')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Lặp lại hàng năm (ví dụ: Tết dương lịch)
            $table->boolean('recurring_yearly')->default(false)->index();

            $table->timestamps();

            // Ràng buộc đơn giản: đảm bảo logic scope ↔ id
            // (không thể enforce bằng SQL thuần, xử lý ở validation/service)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
