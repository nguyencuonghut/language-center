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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->unsignedBigInteger('total');
            $table->enum('status', ['unpaid','partial','paid','refunded'])->default('unpaid')->index();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
