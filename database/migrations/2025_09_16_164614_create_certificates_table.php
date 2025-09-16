<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_certificate', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->string('credential_no')->nullable();
            $table->string('issued_by')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('file_path')->nullable(); // store private, serve via signed URL
            $table->timestamps();

            $table->unique(['teacher_id','certificate_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('teacher_certificate');
        Schema::dropIfExists('certificates');
    }
};
