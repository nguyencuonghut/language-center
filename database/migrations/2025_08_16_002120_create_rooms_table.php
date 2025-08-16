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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('code')->index();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['branch_id','code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
