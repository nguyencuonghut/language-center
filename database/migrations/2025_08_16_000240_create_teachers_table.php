<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('code')->unique();
            $table->string('name')->index();

            $table->string('phone')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();

            $table->string('national_id')->nullable(); // cast encrypted trong model
            $table->string('photo_path')->nullable();  // lưu storage/app/private

            // Trình độ học vấn (ENUM): bachelor | engineer | master | phd | other
            $table->enum('education_level', ['bachelor','engineer','master','phd','other'])->nullable();

            // Tình trạng làm việc (ENUM): active | inactive | terminated | on_leave | adjunct
            $table->enum('status', ['active','inactive','terminated','on_leave','adjunct'])
                ->default('active');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('teachers');
    }
};
