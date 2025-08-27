<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                 // PR-2025-08-001...
            $table->unsignedBigInteger('branch_id')->nullable()->index(); // có thể tổng hợp nhiều chi nhánh
            $table->date('period_from')->index();
            $table->date('period_to')->index();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->enum('status', ['draft','approved','locked'])->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Ngoại lệ: branch_id có thể null (kỳ trả lương toàn hệ thống)
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnUpdate()->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('payrolls');
    }
};
