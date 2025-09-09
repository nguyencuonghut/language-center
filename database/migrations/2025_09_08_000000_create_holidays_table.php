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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            // null = toàn hệ thống; cụ thể = áp dụng cho 1 chi nhánh
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnUpdate()->nullOnDelete();

            $table->date('date')->index();                  // ngày nghỉ
            $table->string('name');                         // tên ngày nghỉ (Tết, Quốc khánh…)
            $table->boolean('is_closed')->default(true);    // true = nghỉ (khóa buổi), false = chỉ ghi chú
            $table->boolean('repeats_annually')->default(false); // lặp hằng năm theo MM-DD

            // Phạm vi áp dụng: global/branch (suy ra từ branch_id, nhưng để dễ filter nhanh)
            $table->enum('scope', ['global','branch'])->default('global')->index();

            $table->timestamps();

            // Tránh trùng trong cùng phạm vi
            // - global: branch_id = null
            // - branch: branch_id != null
            $table->unique(['date','branch_id']);
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
