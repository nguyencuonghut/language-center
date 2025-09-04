<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('from_class_id')->constrained('classrooms')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('to_class_id')->constrained('classrooms')->cascadeOnUpdate()->cascadeOnDelete();
            
            // Transfer details
            $table->date('effective_date');
            $table->unsignedSmallInteger('start_session_no')->default(1);
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            
            // Status tracking
            $table->enum('status', ['active', 'reverted', 'retargeted'])->default('active')->index();
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->foreignId('reverted_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Retarget tracking
            $table->foreignId('retargeted_to_class_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->timestamp('retargeted_at')->nullable();
            $table->foreignId('retargeted_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Financial tracking
            $table->decimal('transfer_fee', 10, 2)->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['student_id', 'status']);
            $table->index(['from_class_id', 'to_class_id']);
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
