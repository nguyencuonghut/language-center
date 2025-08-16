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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnUpdate()->cascadeOnDelete();
            $table->enum('type', ['tuition','adjust','transfer_out','transfer_in','refund'])->default('tuition')->index();
            $table->string('description')->nullable();
            $table->integer('qty')->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('amount'); // qty * unit_price hoặc nhập trực tiếp
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
